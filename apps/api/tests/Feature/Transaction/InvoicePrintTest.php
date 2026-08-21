<?php

namespace Tests\Feature\Transaction;

use App\Enums\ClinicRole;
use App\Models\Activity;
use App\Models\Patient;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pencatatan cetak nota.
 *
 * Dipanggil tepat sebelum dialog cetak terbuka, jadi nomor cetakan yang
 * tertera di kertas harus sama dengan yang baru tersimpan. Nota yang dicetak
 * ulang perlu terlihat di log audit — selisih kertas dan catatan kas kerap
 * ditelusuri dari sana.
 */
class InvoicePrintTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function makeTransaction(): Transaction
    {
        return Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'cashier_id' => auth()->id(),
            'subtotal' => 250000,
        ]);
    }

    public function test_first_print_sets_the_counter_to_one(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction();

        // Dibaca ulang dari basis data: nilai bawaan kolom baru menempel di
        // baris, bukan di instance hasil factory.
        $this->assertSame(0, $transaction->fresh()->print_count);
        $this->assertNull($transaction->fresh()->last_printed_at);

        $this->postJson($this->tenantUrl("transactions/{$transaction->id}/invoice/print"))
            ->assertOk()
            ->assertJsonPath('data.print_count', 1);

        $transaction->refresh();
        $this->assertSame(1, $transaction->print_count);
        $this->assertNotNull($transaction->last_printed_at);
    }

    public function test_reprinting_increments_the_counter(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction();
        $url = $this->tenantUrl("transactions/{$transaction->id}/invoice/print");

        $this->postJson($url)->assertOk();
        $this->postJson($url)->assertOk();
        $this->postJson($url)->assertJsonPath('data.print_count', 3);

        $this->assertSame(3, $transaction->fresh()->print_count);
    }

    /** Cetak ulang harus bisa dibedakan dari cetak pertama di log audit. */
    public function test_reprint_is_recorded_distinctly_in_the_audit_log(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction();
        $url = $this->tenantUrl("transactions/{$transaction->id}/invoice/print");

        $this->postJson($url)->assertOk();
        $first = Activity::where('event', 'transaction.printed')->latest('id')->first();

        $this->postJson($url)->assertOk();
        $second = Activity::where('event', 'transaction.printed')->latest('id')->first();

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertStringContainsString($transaction->invoice_number, $first->description);
        $this->assertStringNotContainsString('ulang', $first->description);
        $this->assertStringContainsString('ulang', $second->description);
        $this->assertSame(1, $second->properties['old']['print_count']);
        $this->assertSame(2, $second->properties['new']['print_count']);
    }

    /** Kasir memegang nota, jadi ia harus bisa mencetaknya. */
    public function test_cashier_may_record_a_print(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $transaction = $this->makeTransaction();

        $this->postJson($this->tenantUrl("transactions/{$transaction->id}/invoice/print"))
            ->assertOk();
    }

    /** Terapis tidak mengurus tagihan sama sekali. */
    public function test_therapist_cannot_record_a_print(): void
    {
        $this->actingAsClinicUser(ClinicRole::Therapist);
        $transaction = $this->makeTransaction();

        $this->postJson($this->tenantUrl("transactions/{$transaction->id}/invoice/print"))
            ->assertForbidden();

        $this->assertSame(0, $transaction->fresh()->print_count);
    }

    /** Nota klinik lain tidak boleh tersentuh, bahkan lewat id yang ditebak. */
    public function test_an_invoice_from_another_clinic_is_not_found(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction();

        $other = $this->createTenant('klinik-lain');
        $this->actingAsClinicUser(ClinicRole::Admin, $other);

        $this->postJson($this->tenantUrl("transactions/{$transaction->id}/invoice/print", $other))
            ->assertNotFound();

        $this->assertSame(0, $transaction->fresh()->print_count);
    }
}
