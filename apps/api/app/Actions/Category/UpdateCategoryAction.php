<?php

namespace App\Actions\Category;

use App\Actions\LogAuditAction;
use App\Models\Category;

class UpdateCategoryAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Category $category, array $data): Category
    {
        $old = $category->getAttributes();

        $category->fill($data)->save();

        app(LogAuditAction::class)->handle(
            'category.updated',
            $category,
            auth()->user(),
            ['old' => $old, 'new' => $category->getAttributes()],
            'Memperbarui kategori '.$category->name.'.',
        );

        return $category;
    }
}
