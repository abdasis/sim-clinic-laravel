<?php

namespace Tests\Feature\Transaction;

use App\Actions\Transaction\CancelTransactionAction;
use App\Actions\Transaction\PayTransactionAction;
use App\Actions\Transaction\SoftDeleteTransactionAction;
use App\Enums\PaymentStatus;
use App\Models\Activity;
use App\Models\Patient;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pembayaran bisa dicicil: status bergerak dari belum dibayar ke dibayar
 * sebagian, lalu lunas. Saldo terbayar diakumulasi di transaksi supaya
 * sisa tagihan bisa dibaca tanpa menjumlah ulang seluruh pembayaran.
 */
class TransactionPaymentTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_partial_payment_moves_status_to_partially_paid(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction();

        $result = app(PayTransactionAction::class)->handle($transaction, [
            'method' => 'cash',
            'amount' => 40000,
            'paid_at' => now(),
        ]);

        $this->assertSame(PaymentStatus::PartiallyPaid, $result['payment_status']);
        $this->assertSame(40000.0, $result['paid_amount']);
        $this->assertSame(60000.0, $result['outstanding']);
        $this->assertFalse($result['overpaid']);
    }

    public function test_payments_accumulate_until_paid(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction();

        app(PayTransactionAction::class)->handle($transaction, [
            'method' => 'cash', 'amount' => 40000, 'paid_at' => now(),
        ]);
        $result = app(PayTransactionAction::class)->handle($transaction, [
            'method' => 'cash', 'amount' => 60000, 'paid_at' => now(),
        ]);

        $this->assertSame(PaymentStatus::Paid, $result['payment_status']);
        $this->assertSame(0.0, $result['outstanding']);
        $this->assertSame('100000.00', $transaction->fresh()->paid_amount);
    }

    public function test_overpayment_is_flagged_but_accepted(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction();

        $result = app(PayTransactionAction::class)->handle($transaction, [
            'method' => 'cash', 'amount' => 150000, 'paid_at' => now(),
        ]);

        $this->assertTrue($result['overpaid']);
        $this->assertSame(PaymentStatus::Paid, $result['payment_status']);
        $this->assertSame(0.0, $result['outstanding'], 'Sisa bayar tidak boleh negatif.');
    }

    public function test_payment_is_recorded_narratively_with_status_change(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction();

        app(PayTransactionAction::class)->handle($transaction, [
            'method' => 'cash', 'amount' => 40000, 'paid_at' => now(),
        ]);

        $activity = Activity::where('event', 'pos.payment.created')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString($transaction->invoice_number, $activity->description);
        $this->assertSame('unpaid', $activity->properties['old']['payment_status']);
        $this->assertSame('partially_paid', $activity->properties['new']['payment_status']);
        $this->assertSame(40000, $activity->properties['amount']);
    }

    public function test_transaction_cannot_be_cancelled_twice(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction();

        app(CancelTransactionAction::class)->handle($transaction);

        $this->expectException(HttpException::class);

        app(CancelTransactionAction::class)->handle($transaction->fresh());
    }

    public function test_soft_deleted_transaction_leaves_the_active_list(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction();

        app(SoftDeleteTransactionAction::class)->handle($transaction);

        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(1, Transaction::withTrashed()->count());

        $activity = Activity::where('event', 'pos.transaction.deleted')->latest('id')->first();
        $this->assertNotNull($activity);
        $this->assertStringContainsString($transaction->invoice_number, $activity->description);
    }

    public function test_invoice_number_is_unique_per_tenant_per_day(): void
    {
        $this->actingAsClinicUser();

        $numbers = collect(range(1, 5))->map(function (): string {
            $number = \DB::transaction(fn (): string => Transaction::generateInvoiceNumber());

            Transaction::factory()->create([
                'tenant_id' => $this->tenant->id,
                'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
                'cashier_id' => auth()->id(),
                'invoice_number' => $number,
            ]);

            return $number;
        });

        $this->assertSame(5, $numbers->unique()->count());
        $this->assertStringStartsWith('INV-'.now()->format('Ymd'), $numbers->first());
    }

    /**
     * Reproduksi laporan kasir: tagihan 500 ribu dibayar tunai 2 juta lewat
     * endpoint POS. Notanya harus lunas, bukan tetap menagih penuh.
     */
    public function test_cash_payment_endpoint_settles_the_bill(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction(500000);

        $response = $this->postJson($this->tenantUrl("transactions/{$transaction->id}/payments"), [
            'method' => 'cash',
            'amount' => 2000000,
            'paid_at' => now()->toIso8601String(),
        ]);

        $response->assertOk();
        $this->assertSame('paid', $response->json('meta.payment_status'));
        $this->assertTrue($response->json('meta.overpaid'));

        $transaction->refresh();
        $this->assertSame(PaymentStatus::Paid, $transaction->payment_status);
        $this->assertSame(0.0, $transaction->outstandingAmount());
    }

    /**
     * Cicilan lewat endpoint: statusnya harus berhenti di "dibayar sebagian",
     * bukan melompat ke lunas.
     */
    public function test_partial_payment_endpoint_keeps_the_bill_open(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction(500000);

        $response = $this->postJson($this->tenantUrl("transactions/{$transaction->id}/payments"), [
            'method' => 'transfer',
            'amount' => 200000,
            'paid_at' => now()->toIso8601String(),
        ]);

        $response->assertOk();
        $this->assertSame('partially_paid', $response->json('meta.payment_status'));
        $this->assertFalse($response->json('meta.overpaid'));
        $this->assertEqualsWithDelta(300000, $response->json('meta.outstanding'), 0.001);
    }

    public function test_cancel_endpoint_marks_the_transaction_cancelled(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction();

        $this->postJson($this->tenantUrl("transactions/{$transaction->id}/cancel"))
            ->assertOk();

        $this->assertNotNull($transaction->fresh()->cancelled_at);
    }

    public function test_delete_endpoint_removes_a_cancelled_transaction_from_the_list(): void
    {
        $this->actingAsClinicUser();
        $transaction = $this->makeTransaction();

        $this->postJson($this->tenantUrl("transactions/{$transaction->id}/cancel"))->assertOk();
        $this->deleteJson($this->tenantUrl("transactions/{$transaction->id}"))->assertOk();

        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
    }

    private function makeTransaction(float $subtotal = 100000): Transaction
    {
        return Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'cashier_id' => auth()->id(),
            'subtotal' => $subtotal,
        ]);
    }
}
