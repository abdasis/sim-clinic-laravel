<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\DiscountType;
use App\Enums\PromoStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[ScopedBy([TenantScope::class])]
class Promo extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'status' => PromoStatus::class,
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PromoItem::class);
    }

    /**
     * Promo yang benar-benar berlaku pada satu tanggal: aktif dan tanggalnya
     * berada di dalam rentang, batas awal dan akhir ikut terhitung.
     */
    public function scopeActiveOn(Builder $query, ?Carbon $date = null): Builder
    {
        $date ??= Carbon::today();

        return $query->where('status', PromoStatus::Active)
            ->whereDate('starts_at', '<=', $date)
            ->whereDate('ends_at', '>=', $date);
    }

    /**
     * Harga setelah promo ini diterapkan.
     */
    public function applyTo(float $price): float
    {
        return $this->discount_type->apply($price, (float) $this->discount_value);
    }

    public function isRunningOn(?Carbon $date = null): bool
    {
        $date ??= Carbon::today();

        return $this->status === PromoStatus::Active
            && $this->starts_at?->startOfDay()->lessThanOrEqualTo($date)
            && $this->ends_at?->endOfDay()->greaterThanOrEqualTo($date);
    }
}
