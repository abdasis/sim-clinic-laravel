<?php

namespace App\Actions;

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
        return DB::transaction(function () use ($transaction): Transaction {
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
    }
}
