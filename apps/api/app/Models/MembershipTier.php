<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\MembershipStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tingkat keanggotaan — label klasifikasi pasien, tanpa potongan harga.
 *
 * Sempat membawa potongan otomatis (persen/nominal), tapi klinik memilih
 * poin loyalitas saja sebagai manfaatnya — dua mekanisme sekaligus cuma
 * menambah satu lapis hitungan lagi di kasir. Tingkat tetap berguna sebagai
 * dasar klasifikasi (dan manfaat lain di masa depan), hanya saja tidak lagi
 * menyentuh tagihan.
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
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
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
}
