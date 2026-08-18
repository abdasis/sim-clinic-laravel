<?php

namespace App\Policies;

use App\Models\User;

/**
 * Kategori: Admin = CRUD; Dokter/Terapis = view (butuh melihat kategori
 * layanan); Kasir = tidak ada.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('category.view');
    }

    public function view(User $user): bool
    {
        return $user->can('category.view');
    }

    public function create(User $user): bool
    {
        return $user->can('category.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('category.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('category.manage');
    }
}
