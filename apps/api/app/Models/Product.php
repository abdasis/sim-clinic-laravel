<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\ServiceStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([TenantScope::class])]
class Product extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'unit', 'stock_balance', 'min_threshold', 'price', 'status'];

    protected $appends = ['is_low_stock'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'status' => ServiceStatus::class,
            'stock_balance' => 'integer',
            'min_threshold' => 'integer',
        ];
    }

    /**
     * FR-065: stok menipis bila saldo <= ambang minimum.
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->stock_balance <= $this->min_threshold;
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }
}
