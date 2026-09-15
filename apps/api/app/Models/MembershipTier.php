<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\DiscountType;
use App\Enums\MembershipStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tingkat keanggotaan berikut potongan yang menyertainya.
 *
 * Besaran potongannya hidup di sini, bukan menempel di tiap pasien: klinik
 * sesekali menaikkan manfaatnya, dan kalau angkanya tersebar di ratusan baris
 * pasien, satu perubahan berarti menyunting semuanya — dan yang terlewat
 * diam-diam memakai angka lama.
 */
#[ScopedBy([TenantScope::class])]
class MembershipTier extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * Bawaan yang sama dengan kolom di basis data, supaya baris yang baru
     * dibuat sudah membawa nilainya sebelum sempat dibaca ulang.
     */
    protected $attributes = [
        'status' => 'active',
        'stacks_with_promo' => false,
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'stacks_with_promo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'stacks_with_promo' => 'boolean',
            'status' => MembershipStatus::class,
        ];
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }

    /** Harga setelah potongan tingkat ini. */
    public function applyTo(float $amount): float
    {
        return $this->discount_type->apply($amount, (float) $this->discount_value);
    }
}
