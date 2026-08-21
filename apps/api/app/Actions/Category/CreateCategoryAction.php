<?php

namespace App\Actions\Category;

use App\Actions\LogAuditAction;
use App\Models\Category;

class CreateCategoryAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Category
    {
        $category = Category::create($data);

        // Status berasal dari default kolom, jadi instance hasil create
        // belum memuatnya sampai di-refresh — tanpa ini responsnya, dan
        // catatan auditnya, mengirim status kosong.
        $category->refresh();

        app(LogAuditAction::class)->handle(
            'category.created',
            $category,
            auth()->user(),
            ['attributes' => $category->getAttributes()],
            'Membuat kategori '.$category->name.' untuk '.$category->type->label().'.',
        );

        return $category;
    }
}
