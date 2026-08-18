<?php

namespace App\Actions\Category;

use App\Actions\LogAuditAction;
use App\Enums\ServiceStatus;
use App\Models\Category;

/**
 * Arsipkan kategori: hilang dari pilihan formulir, tapi item yang sudah
 * memakainya tetap menampilkan namanya.
 */
class ArchiveCategoryAction
{
    public function handle(Category $category): Category
    {
        $old = $category->status;

        $category->forceFill(['status' => ServiceStatus::Archived])->save();

        app(LogAuditAction::class)->handle(
            'category.archived',
            $category,
            auth()->user(),
            ['old' => ['status' => $old], 'new' => ['status' => $category->status]],
            'Mengarsipkan kategori '.$category->name.'.',
        );

        return $category;
    }
}
