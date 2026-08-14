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

    private function makeTransaction(): Transaction
    {
        return Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'cashier_id' => auth()->id(),
            'subtotal' => 100000,
        ]);
    }
}
