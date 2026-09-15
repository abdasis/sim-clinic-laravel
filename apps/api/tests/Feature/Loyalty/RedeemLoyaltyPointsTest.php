<?php

namespace Tests\Feature\Loyalty;

use App\Actions\Transaction\CancelTransactionAction;
use App\Actions\Transaction\PayTransactionAction;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Penukaran poin jadi potongan di kasir.
 *
 * Poin yang hanya bisa dikumpulkan tapi tidak pernah bisa dipakai cuma angka
 * di layar. Yang dijaga di sini justru sisi yang merugikan kalau salah:
 * saldo pasien tidak boleh terpotong lebih dari yang ditukar, penukaran yang
 * melebihi saldo harus ditolak terang-terangan, dan nota yang batal harus
 * mengembalikan poinnya utuh.
 */
class RedeemLoyaltyPointsTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private Service $service;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();
        $this->patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_member' => true,
            'loyalty_points' => 50,
        ]);
        $this->service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
            'price' => 200000,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function checkout(array $extra = [], int $qty = 1)
    {
        return $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $this->patient->id,
            'items' => [[
                'service_id' => $this->service->id,
                'qty' => $qty,
            ]],
            ...$extra,
        ]);
    }

    public function test_redeeming_points_cuts_the_bill(): void
    {
        $this->checkout(['points_redeemed' => 30])->assertCreated();

        $transaction = Transaction::query()->latest('id')->first();

        // 30 poin x Rp1.000 = Rp30.000 dipotong dari Rp200.000.
        $this->assertSame(30, $transaction->points_redeemed);
        $this->assertEqualsWithDelta(30000.0, (float) $transaction->points_redeemed_amount, 0.01);
        $this->assertEqualsWithDelta(170000.0, (float) $transaction->subtotal, 0.01);
        $this->assertSame(20, $this->patient->fresh()->loyalty_points);
    }

    /**
     * Saldo yang tidak cukup ditolak terang, bukan dipangkas diam-diam:
     * kasir sudah menyebut angka potongannya ke pasien sebelum menekan simpan.
     */
    public function test_redeeming_more_than_the_balance_is_refused(): void
    {
        $this->checkout(['points_redeemed' => 80])->assertStatus(422);

        // Notanya tidak boleh terlanjur terbit dengan potongan yang tidak ada.
        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(50, $this->patient->fresh()->loyalty_points);
    }

    /**
     * Poin memotong yang harus dibayar; ia bukan uang yang bisa diambil
     * pulang sebagai kembalian. Kelebihannya dipangkas, sisanya tetap
     * tersimpan — kasir yang menukar seluruh saldo bermaksud "pakai sebisanya".
     */
    public function test_redeeming_beyond_the_bill_is_capped_and_the_rest_is_kept(): void
    {
        $this->patient->update(['loyalty_points' => 500]);

        // Tagihannya Rp200.000, jadi paling banyak 200 poin yang terpakai.
        $this->checkout(['points_redeemed' => 500])->assertCreated();

        $transaction = Transaction::query()->latest('id')->first();

        $this->assertSame(200, $transaction->points_redeemed);
        $this->assertEqualsWithDelta(0.0, (float) $transaction->subtotal, 0.01);
        $this->assertSame(300, $this->patient->fresh()->loyalty_points);
    }

    /** Penukaran menyusul potongan nota, bukan mendahuluinya. */
    public function test_points_apply_on_top_of_the_invoice_discount(): void
    {
        $this->checkout([
            'discount_type' => 'percent',
            'discount_value' => 10,
            'points_redeemed' => 30,
        ])->assertCreated();

        $transaction = Transaction::query()->latest('id')->first();

        // 200.000 - 10% = 180.000, lalu -30.000 dari poin = 150.000.
        $this->assertEqualsWithDelta(20000.0, (float) $transaction->discount_amount, 0.01);
        $this->assertEqualsWithDelta(150000.0, (float) $transaction->subtotal, 0.01);
    }

    /** Penukaran receh membuat riwayat poin jadi daftar yang tidak bercerita. */
    public function test_a_redemption_below_the_minimum_is_rejected(): void
    {
        $this->checkout(['points_redeemed' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('points_redeemed');
    }

    /** Nol berarti tidak menukar, bukan penukaran di bawah minimum. */
    public function test_zero_means_no_redemption_at_all(): void
    {
        $this->checkout(['points_redeemed' => 0])->assertCreated();

        $transaction = Transaction::query()->latest('id')->first();

        $this->assertSame(0, $transaction->points_redeemed);
        $this->assertEqualsWithDelta(200000.0, (float) $transaction->subtotal, 0.01);
        $this->assertSame(50, $this->patient->fresh()->loyalty_points);
    }

    /**
     * Poin didapat dari yang benar-benar dibayar, bukan dari harga sebelum
     * ditukar — kalau tidak, pasien memutar poin yang sama berulang-ulang.
     */
    public function test_points_earned_come_from_what_is_actually_paid(): void
    {
        $this->checkout(['points_redeemed' => 30])->assertCreated();

        $transaction = Transaction::query()->latest('id')->first();

        app(PayTransactionAction::class)->handle($transaction, [
            'method' => 'cash',
            'amount' => 170000,
            'paid_at' => now(),
        ]);

        // floor(170.000 / 10.000) = 17 poin, bukan 20 dari harga penuh.
        $this->assertSame(17, $transaction->fresh()->points_earned);
        // 50 - 30 ditukar + 17 didapat.
        $this->assertSame(37, $this->patient->fresh()->loyalty_points);
    }

    /** Notanya batal, jadi potongannya pun batal — poinnya kembali utuh. */
    public function test_cancelling_gives_the_redeemed_points_back(): void
    {
        $this->checkout(['points_redeemed' => 30])->assertCreated();

        $transaction = Transaction::query()->latest('id')->first();
        $this->assertSame(20, $this->patient->fresh()->loyalty_points);

        app(CancelTransactionAction::class)->handle($transaction);

        $this->assertSame(50, $this->patient->fresh()->loyalty_points);
        $this->assertSame(0, $transaction->fresh()->points_redeemed);
    }

    /**
     * Nota yang sudah lunas lalu dibatalkan bergerak dua arah sekaligus:
     * poin yang ditukar kembali, poin yang didapat ditarik.
     */
    public function test_cancelling_a_paid_invoice_settles_both_directions(): void
    {
        $this->checkout(['points_redeemed' => 30])->assertCreated();

        $transaction = Transaction::query()->latest('id')->first();

        app(PayTransactionAction::class)->handle($transaction, [
            'method' => 'cash',
            'amount' => 170000,
            'paid_at' => now(),
        ]);

        $this->assertSame(37, $this->patient->fresh()->loyalty_points);

        app(CancelTransactionAction::class)->handle($transaction->fresh());

        // Kembali ke saldo semula: 37 + 30 dikembalikan - 17 ditarik = 50.
        $this->assertSame(50, $this->patient->fresh()->loyalty_points);
    }

    /** Nilai rupiahnya tetap tercetak di nota batal, cuma poinnya yang pulang. */
    public function test_a_cancelled_invoice_still_shows_what_it_once_charged(): void
    {
        $this->checkout(['points_redeemed' => 30])->assertCreated();

        $transaction = Transaction::query()->latest('id')->first();

        app(CancelTransactionAction::class)->handle($transaction);

        $this->assertEqualsWithDelta(
            30000.0,
            (float) $transaction->fresh()->points_redeemed_amount,
            0.01,
        );
    }

    /** Penukaran ikut terbawa ke nota yang dibaca frontend. */
    public function test_the_redemption_is_reported_on_the_invoice(): void
    {
        $id = $this->checkout(['points_redeemed' => 30])
            ->assertCreated()
            ->json('data.id');

        $this->getJson($this->tenantUrl("transactions/{$id}"))
            ->assertOk()
            ->assertJsonPath('data.points_redeemed', 30);
    }
}
