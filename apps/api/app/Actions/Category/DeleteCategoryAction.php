<?php

namespace App\Actions\Category;

use App\Actions\LogAuditAction;
use App\Models\Category;

/**
 * Hapus permanen. Ditolak bila masih ada item yang memakainya: pivot-nya
 * cascade, jadi menghapus kategori diam-diam akan melepas kategori dari
 * seluruh layanan/produk/pengeluaran yang menunjuknya.
 */
class DeleteCategoryAction
{
    public function handle(Category $category): void
    {
        $used = $category->usageCount();

        abort_if($used > 0, 422, __('category.in_use', ['count' => $used]));

        $attributes = $category->getAttributes();
        $name = $category->name;

        $category->delete();

        app(LogAuditAction::class)->handle(
            'category.deleted',
            null,
            auth()->user(),
            ['old' => $attributes],
            'Menghapus permanen kategori '.$name.'.',
        );
    }
}
