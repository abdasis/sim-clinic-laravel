<?php

namespace App\Actions\Inventory;

use App\Actions\LogAuditAction;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Catat satu mutasi stok beserta saldo sesudahnya, lalu perbarui saldo
 * produk. Jejaknya immutable — koreksi dilakukan dengan mutasi baru,
 * bukan dengan mengubah baris lama.
 */
class RecordStockMovementAction
{
    public function __construct(private readonly LogAuditAction $audit) {}

    public function handle(
        Product $product,
        StockMovementType $type,
        int $quantity,
        int $balanceAfter,
        ?string $note = null,
        ?Model $related = null,
    ): StockMovement {
        try {
            $movement = StockMovement::create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $balanceAfter,
                // Alias morph map, bukan nama kelas penuh.
                'related_type' => $related?->getMorphClass(),
                'related_id' => $related?->getKey(),
                'note' => $note,
                'created_at' => now(),
            ]);

            $product->update(['stock_balance' => $balanceAfter]);
        } catch (\Throwable $e) {
            Log::error('Gagal mencatat mutasi stok.', [
                'exception' => $e,
                'product_id' => $product->id,
                'type' => $type->value,
                'quantity' => $quantity,
            ]);

            throw $e;
        }

        $this->audit->handle(
            action: 'inventory.stock.adjusted',
            subject: $movement,
            causer: auth()->user() instanceof User ? auth()->user() : null,
            context: ['attributes' => $movement->getAttributes()],
            description: sprintf(
                'Menyesuaikan stok %s — %s %d, saldo menjadi %d.',
                $product->name,
                $type->label(),
                $quantity,
                $balanceAfter,
            ),
        );

        return $movement;
    }
}
