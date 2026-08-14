<?php

namespace App\Policies;

use App\Models\User;

/**
 * Product: Admin = CRUD; peran lain tidak ada (R2 matriks).
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('product.view');
    }

    public function view(User $user): bool
    {
        return $user->can('product.view');
    }

    public function create(User $user): bool
    {
        return $user->can('product.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('product.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('product.manage');
    }
}
