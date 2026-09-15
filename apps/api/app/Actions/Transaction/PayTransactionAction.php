<?php

namespace App\Actions\Transaction;

use App\Actions\LogAuditAction;
use App\Actions\Loyalty\AdjustLoyaltyPointsAction;
use App\Enums\PaymentStatus;
use App\Models\Transaction;
use App\Support\LoyaltyPoints;
use Illuminate\Support\Facades\DB;

/**
 * Catat pembayaran dan selaraskan status (FR-055): belum dibayar, dibayar
 * sebagian, atau lunas. Kelebihan bayar hanya diperingatkan — tidak ada
 * saldo otomatis.
 */
class PayTransactionAction
{
    /**
     * @param  array{method:string, amount:float|string, paid_at:mixed}  $data
     * @return array{payment_status:PaymentStatus, overpaid:bool, paid_amount:float, outstanding:float}
     */
    public function handle(Transaction $transaction, array $data): array
    {
        $result = DB::transaction(function () use ($transaction, $data): array {
            // Kunci barisnya supaya dua pembayaran bersamaan tidak saling menimpa.
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->getKey());

            $locked->payments()->create([
                'method' => $data['method'],
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'],
            ]);

            $oldStatus = $locked->payment_status;
            $paidAmount = (float) $locked->paid_amount + (float) $data['amount'];
            $newStatus = $this->resolveStatus($paidAmount, (float) $locked->subtotal);

            $locked->update([
                'paid_amount' => $paidAmount,
                'payment_status' => $newStatus,
            ]);

            // Poin diberikan tepat sekali, di transisi menuju lunas — bukan
            // di tiap setoran. Pembayaran bertahap yang belum genap tidak
            // menghasilkan poin sebagian, dan pembayaran susulan setelah
            // lunas (kelebihan bayar) tidak menambah lagi karena statusnya
            // sudah lunas sebelum baris ini tercapai.
            $this->awardLoyaltyPoints($locked, $oldStatus, $newStatus);

            return [
                'transaction' => $locked,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'paid_amount' => $paidAmount,
                'amount' => (float) $data['amount'],
            ];
        });

        $this->recordAudit($result);

        $transaction->refresh();

        return [
            'payment_status' => $result['new_status'],
            'overpaid' => $result['paid_amount'] > (float) $transaction->subtotal,
            'paid_amount' => $result['paid_amount'],
            'outstanding' => $transaction->outstandingAmount(),
        ];
    }

    private function resolveStatus(float $paidAmount, float $subtotal): PaymentStatus
    {
        if ($paidAmount <= 0) {
            return PaymentStatus::Unpaid;
        }

        return $paidAmount >= $subtotal ? PaymentStatus::Paid : PaymentStatus::PartiallyPaid;
    }

    /**
     * Snapshot poin ke nota, lalu tambahkan ke saldo pasien — sekali, pada
     * transisi ke lunas. Dihitung dari `subtotal` (yang benar-benar
     * ditagihkan setelah semua potongan), bukan dari uang yang diserahkan:
     * pasien mendapat poin dari belanjanya, bukan dari kelebihan bayarnya.
     */
    private function awardLoyaltyPoints(Transaction $transaction, PaymentStatus $oldStatus, PaymentStatus $newStatus): void
    {
        if ($oldStatus === PaymentStatus::Paid || $newStatus !== PaymentStatus::Paid) {
            return;
        }

        $earned = LoyaltyPoints::earn((float) $transaction->subtotal);

        if ($earned <= 0) {
            return;
        }

        $transaction->update(['points_earned' => $earned]);

        app(AdjustLoyaltyPointsAction::class)->handle(
            $transaction->patient,
            $earned,
            'nota '.$transaction->invoice_number,
            $transaction,
        );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function recordAudit(array $result): void
    {
        $transaction = $result['transaction'];

        app(LogAuditAction::class)->handle(
            'pos.payment.created',
            $transaction,
            auth()->user(),
            [
                'amount' => $result['amount'],
                'paid_amount' => $result['paid_amount'],
                'old' => ['payment_status' => $result['old_status']->value],
                'new' => ['payment_status' => $result['new_status']->value],
            ],
            'Mencatat pembayaran '.$transaction->invoice_number.' — status '
                .$result['old_status']->label().' menjadi '.$result['new_status']->label().'.',
        );
    }
}
