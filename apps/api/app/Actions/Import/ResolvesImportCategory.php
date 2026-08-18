<?php

namespace App\Actions\Import;

use App\Enums\CategorizableType;
use App\Models\Category;

/**
 * Kategori pada template diisi dengan nama bebas: yang belum ada dibuat saat
 * impor, bukan ditolak. Nama yang sama dalam satu berkas hanya melahirkan
 * satu kategori.
 */
trait ResolvesImportCategory
{
    public function categoryId(string $name, CategorizableType $type): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return Category::query()->firstOrCreate(
            ['name' => $name, 'type' => $type],
            ['status' => 'active'],
        )->id;
    }
}
