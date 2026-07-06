<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Catat pembayaran; set payment_status=paid bila total bayar >= subtotal (FR-055).
 * Kelebihan bayar hanya peringatan (edge case), tidak ada saldo otomatis.
 */
class PayTransactionAction
{
    public function handle(Transaction $transaction, array $data): array
    {
        return DB::transaction(function () use ($transaction, $data): array {
            $transaction->payments()->create([
                'method' => $data['method'],
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'],
            ]);

            $totalPaid = (float) $transaction->payments()->sum('amount');
            $subtotal = (float) $transaction->subtotal;

            if ($totalPaid >= $subtotal) {
                $transaction->update(['payment_status' => PaymentStatus::Paid]);
            }

            return [
                'payment_status' => $transaction->fresh()->payment_status,
                'overpaid' => $totalPaid > $subtotal,
            ];
        });
    }
}
