<?php

namespace Tests\Feature\Loyalty;

use App\Actions\Transaction\CancelTransactionAction;
use App\Actions\Transaction\PayTransactionAction;
use App\Models\Patient;
use App\Models\Transaction;
use App\Support\LoyaltyPoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Poin loyalitas: setiap Rp10.000 yang benar-benar dibayar lunas
 * menghasilkan 1 poin, otomatis tanpa kasir perlu menghitung sendiri.
 *
 * Diberikan tepat di transisi menuju lunas — bukan saat nota dibuat (baru
 * janji, belum dibayar) dan bukan per cicilan (pembayaran bertahap yang
 * belum genap tidak menghasilkan poin sebagian).
 *
 * Pasien di berkas ini sengaja dijadikan member: hanya member yang
 * mengumpulkan poin (lihat MemberOnlyPointsTest untuk aturan itu sendiri).
 */
class LoyaltyPointsTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function makeTransaction(float $subtotal = 100_000, ?Patient $patient = null): Transaction
    {
        return Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => ($patient ?? Patient::factory()->create(['tenant_id' => $this->tenant->id, 'is_member' => true]))->id,
            'cashier_id' => auth()->id(),
            'subtotal' => $subtotal,
        ]);
    }

    private function pay(Transaction $transaction, float $amount): array
    {
        return app(PayTransactionAction::class)->handle($transaction, [
            'method' => 'cash',
            'amount' => $amount,
            'paid_at' => now(),
        ]);
    }

    public function test_paying_in_full_earns_points_from_the_subtotal(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction(105_000);

        $this->pay($transaction, 105_000);

        // floor(105.000 / 10.000) = 10 poin, sisa 5.000 dibuang bukan dibulatkan.
        $this->assertSame(10, $transaction->fresh()->points_earned);
        $this->assertSame(10, $transaction->patient->fresh()->loyalty_points);
    }

    /** Pembayaran yang belum genap tidak menghasilkan poin sama sekali. */
    public function test_a_partial_payment_earns_nothing_yet(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction(100_000);

        $this->pay($transaction, 40_000);

        $this->assertSame(0, $transaction->fresh()->points_earned);
        $this->assertSame(0, $transaction->patient->fresh()->loyalty_points);
    }

    /**
     * Poin diberikan pada cicilan yang MELUNASI, dihitung dari seluruh
     * subtotal — bukan cuma dari setoran terakhir.
     */
    public function test_points_land_on_the_installment_that_completes_payment(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction(100_000);

        $this->pay($transaction, 40_000);
        $this->pay($transaction, 60_000);

        $this->assertSame(10, $transaction->fresh()->points_earned);
        $this->assertSame(10, $transaction->patient->fresh()->loyalty_points);
    }

    /** Kelebihan bayar setelah lunas tidak menambah poin lagi. */
    public function test_overpaying_an_already_paid_invoice_does_not_earn_more(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction(100_000);

        $this->pay($transaction, 100_000);
        $this->pay($transaction, 20_000);

        $this->assertSame(10, $transaction->fresh()->points_earned);
        $this->assertSame(10, $transaction->patient->fresh()->loyalty_points);
    }

    /** Di bawah Rp10.000 belum genap satu poin pun. */
    public function test_less_than_ten_thousand_earns_no_points(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction(9_999);

        $this->pay($transaction, 9_999);

        $this->assertSame(0, $transaction->fresh()->points_earned);
        $this->assertSame(0, $transaction->patient->fresh()->loyalty_points);
    }

    /** Poin dari beberapa nota terkumpul di saldo pasien yang sama. */
    public function test_points_accumulate_across_multiple_invoices(): void
    {
        $this->actingAsClinicUser();
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id, 'is_member' => true]);

        $this->pay($this->makeTransaction(50_000, $patient), 50_000);
        $this->pay($this->makeTransaction(30_000, $patient), 30_000);

        $this->assertSame(8, $patient->fresh()->loyalty_points);
    }

    /** Poin milik satu pasien tidak bocor ke pasien lain. */
    public function test_points_do_not_leak_to_another_patient(): void
    {
        $this->actingAsClinicUser();
        $a = Patient::factory()->create(['tenant_id' => $this->tenant->id, 'is_member' => true]);
        $b = Patient::factory()->create(['tenant_id' => $this->tenant->id, 'is_member' => true]);

        $this->pay($this->makeTransaction(100_000, $a), 100_000);

        $this->assertSame(10, $a->fresh()->loyalty_points);
        $this->assertSame(0, $b->fresh()->loyalty_points);
    }

    /**
     * Membatalkan nota yang sudah lunas menarik kembali poin yang sudah
     * terlanjur diberikan — bukan dibiarkan menggantung di saldo pasien
     * padahal transaksinya sendiri sudah batal.
     */
    public function test_cancelling_a_paid_invoice_reclaims_its_points(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction(100_000);

        $this->pay($transaction, 100_000);
        $this->assertSame(10, $transaction->patient->fresh()->loyalty_points);

        app(CancelTransactionAction::class)->handle($transaction->fresh());

        $this->assertSame(0, $transaction->patient->fresh()->loyalty_points);
        $this->assertSame(0, $transaction->fresh()->points_earned);
    }

    /** Membatalkan nota yang belum lunas tidak menyentuh saldo poin sama sekali. */
    public function test_cancelling_an_unpaid_invoice_touches_no_points(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction(100_000);

        app(CancelTransactionAction::class)->handle($transaction->fresh());

        $this->assertSame(0, $transaction->patient->fresh()->loyalty_points);
    }

    /**
     * Saldo tidak pernah minus. Poin dari nota ini sudah "terpakai" di
     * tempat lain (kasus nyatanya nanti: ditukar hadiah) — pembatalannya
     * berhenti di nol, bukan mencetak saldo negatif yang membingungkan.
     */
    public function test_reclaiming_never_pushes_the_balance_below_zero(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction(100_000);
        $this->pay($transaction, 100_000);

        $patient = $transaction->patient->fresh();
        $patient->update(['loyalty_points' => 3]);

        app(CancelTransactionAction::class)->handle($transaction->fresh());

        $this->assertSame(0, $patient->fresh()->loyalty_points);
    }

    public function test_the_transaction_response_reports_points_earned(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction(100_000);
        $this->pay($transaction, 100_000);

        $this->getJson($this->tenantUrl("transactions/{$transaction->id}"))
            ->assertOk()
            ->assertJsonPath('data.points_earned', 10);
    }

    public function test_the_patient_response_reports_the_loyalty_balance(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction(100_000);
        $this->pay($transaction, 100_000);

        $this->getJson($this->tenantUrl('patients'))
            ->assertOk()
            ->assertJsonPath('data.0.loyalty_points', 10);
    }
}

/**
 * Kalkulator murni — tanpa DB, tanpa tenant. Diuji terpisah dari alur
 * pembayaran supaya aturan pembulatannya jelas tanpa perlu membaca ulang
 * seluruh alur transaksi.
 */
class LoyaltyPointsCalculatorTest extends TestCase
{
    public function test_it_floors_instead_of_rounding(): void
    {
        $this->assertSame(1, LoyaltyPoints::earn(19_000));
        $this->assertSame(1, LoyaltyPoints::earn(10_000));
        $this->assertSame(0, LoyaltyPoints::earn(9_999));
        $this->assertSame(10, LoyaltyPoints::earn(105_000));
    }

    public function test_it_never_goes_negative(): void
    {
        $this->assertSame(0, LoyaltyPoints::earn(-50_000));
        $this->assertSame(0, LoyaltyPoints::earn(0));
    }
}
