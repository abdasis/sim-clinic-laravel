<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasCategory;
use App\Enums\ProductType;
use App\Enums\ServiceStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([TenantScope::class])]
class Product extends Model
{
    use BelongsToTenant, HasCategory, HasFactory;

    // `unit` string masih ikut terisi selama masa peralihan: kolomnya belum
    // dibuang supaya migrasi FK bisa dibatalkan tanpa kehilangan nilai lama.
    protected $fillable = ['tenant_id', 'name', 'type', 'unit', 'unit_id', 'stock_balance', 'min_threshold', 'price', 'status'];

    protected $appends = ['is_low_stock'];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
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

    /**
     * Selama kolom string `unit` belum dibuang, `$product->unit` tetap
     * mengembalikan teks lamanya — atribut menang atas relasi. Untuk
     * mendapatkan modelnya, pakai `getRelationValue('unit')` atau eager load.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
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
