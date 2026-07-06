<?php

namespace App\Services;

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
            $locked = Product::whereKey($product->id)->lockForUpdate()->first();

            $newBalance = $type->isInbound()
                ? $locked->stock_balance + $quantity
                : $locked->stock_balance - $quantity;

            $movement = StockMovement::create([
                'tenant_id' => $locked->tenant_id,
                'product_id' => $locked->id,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $newBalance,
                'related_type' => $related?->getMorphClass(),
                'related_id' => $related?->getKey(),
                'note' => $note,
                'created_at' => now(),
            ]);

            $locked->update(['stock_balance' => $newBalance]);

            return $movement;
        });
    }
}
