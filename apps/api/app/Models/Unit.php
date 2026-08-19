<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\ServiceStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satuan produk milik satu klinik: pcs, botol, tube, vial.
 *
 * Berbeda dari Category yang melayani tiga jenis entitas lewat pivot
 * polimorfik, satuan hanya menempel di produk — jadi relasinya FK biasa dan
 * tidak butuh kolom `type`.
 */
#[ScopedBy([TenantScope::class])]
class Unit extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'name', 'status'];

    protected function casts(): array
    {
        return [
            'status' => ServiceStatus::class,
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Berapa produk yang masih memakai satuan ini. Dipakai penjaga hapus
     * permanen sekaligus ditampilkan di daftar, supaya admin tahu taruhannya
     * sebelum menekan hapus.
     */
    public function usageCount(): int
    {
        return $this->products()->count();
    }
}
