<?php

namespace Tests\Feature\Promo;

use App\Models\Patient;
use App\Models\Promo;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Promo yang sudah dipasang harus terlihat di katalog kasir.
 *
 * Kasir memuat katalognya lewat endpoint daftar yang sama dengan layar
 * master, jadi bila harga promonya tidak ikut di sana, kasir menagih harga
 * penuh untuk layanan yang sedang didiskon — dan pasien yang datang karena
 * promo itu membayar lebih.
 */
class PosPromoVisibilityTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();
        $this->service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
            'price' => 200000,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
    }

    private function makePromo(array $overrides = []): Promo
    {
        $promo = Promo::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Promo Agustus',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'status' => 'active',
            ...$overrides,
        ]);

        $promo->items()->create([
            'tenant_id' => $this->tenant->id,
            'promotable_type' => 'service',
            'promotable_id' => $this->service->id,
        ]);

        return $promo;
    }

    /** Persis seperti katalog kasir memuatnya. */
    private function catalogRow(): ?array
    {
        return collect(
            $this->getJson($this->tenantUrl('services').'?per_page=100')
                ->assertOk()->json('data'),
        )->firstWhere('id', $this->service->id);
    }

    public function test_the_cashier_catalog_carries_the_promo_price(): void
    {
        $this->makePromo();

        $row = $this->catalogRow();

        $this->assertNotNull($row['promo'], 'katalog kasir tidak membawa promonya');
        $this->assertSame('Promo Agustus', $row['promo']['name']);
        $this->assertEqualsWithDelta(160000.0, (float) $row['promo']['price'], 0.01);
    }

    /** Harga aslinya tetap ikut, supaya kasir bisa menunjukkan coretannya. */
    public function test_the_original_price_is_still_carried(): void
    {
        $this->makePromo();

        $this->assertEqualsWithDelta(200000.0, (float) $this->catalogRow()['price'], 0.01);
    }

    /** Promo yang belum mulai belum boleh memotong apa pun. */
    public function test_a_promo_that_has_not_started_is_not_applied(): void
    {
        $this->makePromo(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(10)]);

        $this->assertNull($this->catalogRow()['promo']);
    }

    /** Promo yang sudah lewat juga tidak. */
    public function test_an_expired_promo_is_not_applied(): void
    {
        $this->makePromo(['starts_at' => now()->subDays(10), 'ends_at' => now()->subDay()]);

        $this->assertNull($this->catalogRow()['promo']);
    }

    /** Promo nonaktif tidak berlaku walau tanggalnya pas. */
    public function test_an_inactive_promo_is_not_applied(): void
    {
        $this->makePromo(['status' => 'inactive']);

        $this->assertNull($this->catalogRow()['promo']);
    }

    /** Promo yang mulai hari ini juga berlaku hari ini. */
    public function test_a_promo_starting_today_applies_today(): void
    {
        $this->makePromo(['starts_at' => now(), 'ends_at' => now()->addDays(7)]);

        $this->assertNotNull($this->catalogRow()['promo']);
    }

    /** Hari terakhirnya masih ikut, bukan berhenti sehari lebih awal. */
    public function test_the_last_day_still_counts(): void
    {
        $this->makePromo(['starts_at' => now()->subDays(7), 'ends_at' => now()]);

        $this->assertNotNull($this->catalogRow()['promo']);
    }

    /** Harga yang tersimpan di nota mengikuti harga promo, bukan harga penuh. */
    public function test_the_transaction_charges_the_promo_price(): void
    {
        $this->makePromo();

        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $id = $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'items' => [['service_id' => $this->service->id, 'qty' => 1]],
        ])->assertCreated()->json('data.id');

        $item = Transaction::query()->find($id)->items()->first();

        $this->assertEqualsWithDelta(160000.0, (float) $item->unit_price, 0.01);
        $this->assertEqualsWithDelta(200000.0, (float) $item->list_price, 0.01);
    }
}
