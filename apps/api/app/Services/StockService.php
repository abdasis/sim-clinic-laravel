<?php

namespace App\Services;

use App\Actions\Inventory\RecordStockMovementAction;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Satu pintu mutasi stok (R7): semua perubahan saldo produk lewat adjust()
 * dalam DB transaction dengan row lock untuk mencegah race condition.
 */
class StockService
{
    public function adjust(
        Product $product,
        StockMovementType $type,
        int $quantity,
        ?string $note = null,
        ?Model $related = null,
    ): StockMovement {
        return DB::transaction(function () use ($product, $type, $quantity, $note, $related): StockMovement {
            // Baris dikunci lebih dulu supaya dua mutasi bersamaan tidak
            // menghitung saldo dari angka yang sama.
            $locked = Product::whereKey($product->id)->lockForUpdate()->first();

            $newBalance = $type->isInbound()
                ? $locked->stock_balance + $quantity
                : $locked->stock_balance - $quantity;

            // Stok fisik tidak bisa minus; menolak di sini menutup semua
            // jalur sekaligus — penyesuaian manual maupun penjualan POS.
            if (! $type->isInbound() && $newBalance < 0) {
                abort(422, __('inventory.insufficient_stock'));
            }

            return app(RecordStockMovementAction::class)
                ->handle($locked, $type, $quantity, $newBalance, $note, $related);
        });
    }
}
