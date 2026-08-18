<?php

namespace App\Concerns;

use App\Models\Category;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Kategori terpusat untuk model yang dikategorikan (layanan, produk,
 * pengeluaran).
 *
 * Relasinya many-to-many karena pivot-nya polimorfik, tapi satu item hanya
 * boleh punya satu kategori — dijaga unique `(categorizable_type,
 * categorizable_id)` di basis data. Karena itu semua jalur baca dan tulis di
 * sini bekerja pada satu nilai, bukan koleksi.
 *
 * Relasinya sengaja dinamai jamak: kolom `category` lama masih ada di ketiga
 * tabel, dan relasi bernama `category` akan kalah oleh atribut kolom itu saat
 * dibaca lewat `$model->category`.
 */
trait HasCategory
{
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    /** Kategori yang sedang menempel, bila ada. */
    public function assignedCategory(): ?Category
    {
        return $this->categories->first();
    }

    /**
     * Pasang atau lepas kategori. `null` berarti item ini tanpa kategori.
     */
    public function syncCategory(?int $categoryId): void
    {
        $this->categories()->sync($categoryId !== null ? [$categoryId] : []);
        $this->unsetRelation('categories');
    }
}
