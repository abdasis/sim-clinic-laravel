<?php

namespace App\Services;

use App\Actions\Category\ArchiveCategoryAction;
use App\Actions\Category\CreateCategoryAction;
use App\Actions\Category\DeleteCategoryAction;
use App\Actions\Category\UpdateCategoryAction;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Use case kategori terpusat untuk layanan, produk, dan pengeluaran.
 */
class CategoryService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category
    {
        return DB::transaction(fn () => app(CreateCategoryAction::class)->handle($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        return DB::transaction(fn () => app(UpdateCategoryAction::class)->handle($category, $data));
    }

    public function archive(Category $category): Category
    {
        return DB::transaction(fn () => app(ArchiveCategoryAction::class)->handle($category));
    }

    /** Hapus permanen; ditolak bila masih dipakai. */
    public function delete(Category $category): void
    {
        DB::transaction(fn () => app(DeleteCategoryAction::class)->handle($category));
    }
}
