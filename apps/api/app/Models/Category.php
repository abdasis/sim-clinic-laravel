<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\CategorizableType;
use App\Enums\ServiceStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Kategori terpusat untuk layanan, produk, dan pengeluaran.
 *
 * Satu tabel melayani ketiganya lewat pivot polimorfik; `type` yang menjaga
 * kategori layanan tidak menempel ke produk.
 *
 * Status memakai ServiceStatus (active/archived) — arti dan alurnya sama
 * persis dengan katalog layanan, jadi tidak perlu enum kedua.
 */
#[ScopedBy([TenantScope::class])]
class Category extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'name', 'type', 'description', 'status'];

    protected function casts(): array
    {
        return [
            'type' => CategorizableType::class,
            'status' => ServiceStatus::class,
        ];
    }

    public function services(): MorphToMany
    {
        return $this->morphedByMany(Service::class, 'categorizable');
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'categorizable');
    }

    public function expenses(): MorphToMany
    {
        return $this->morphedByMany(Expense::class, 'categorizable');
    }

    /**
     * Berapa item yang masih memakai kategori ini. Dipakai penjaga hapus
     * permanen dan ditampilkan di daftar supaya admin tahu sebelum menghapus.
     */
    public function usageCount(): int
    {
        return $this->categorizables()->count();
    }

    /**
     * Baris pivot mentah: satu kueri, tanpa perlu tahu ini layanan, produk,
     * atau pengeluaran.
     */
    public function categorizables(): HasMany
    {
        return $this->hasMany(Categorizable::class);
    }
}
