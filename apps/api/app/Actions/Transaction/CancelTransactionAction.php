<?php

namespace App\Actions\Transaction;

use App\Actions\LogAuditAction;

use App\Enums\StockMovementType;
use App\Models\Transaction;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Batalkan transaksi (FR-058): kembalikan stok tiap item produk via StockService
 * (type rollback), lalu tandai cancelled_at.
 */
class CancelTransactionAction
{
    public function handle(Transaction $transaction): Transaction
    {
        // Membatalkan dua kali akan mengembalikan stok berlipat.
        if ($transaction->cancelled_at !== null) {
            abort(409, __('pos.already_cancelled'));
        }

        $transaction = DB::transaction(function () use ($transaction): Transaction {
            $transaction->loadMissing('items.product');

            foreach ($transaction->items as $item) {
                if ($item->product !== null) {
                    app(StockService::class)->adjust(
                        $item->product,
                        StockMovementType::Rollback,
                        $item->qty,
                        null,
                        $transaction,
                    );
                }
            }

            $transaction->update(['cancelled_at' => now()]);

            return $transaction;
        });

        app(LogAuditAction::class)->handle(
            'pos.transaction.cancelled',
            $transaction,
            auth()->user(),
            [
                'invoice_number' => $transaction->invoice_number,
                'old' => ['cancelled_at' => null],
                'new' => ['cancelled_at' => $transaction->cancelled_at?->toIso8601String()],
            ],
            'Membatalkan transaksi '.$transaction->invoice_number.'.',
        );

        return $transaction;
    }
}
