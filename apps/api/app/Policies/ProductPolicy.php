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
        return $user->can('clinic.access', ['product', 'r']);
    }

    public function view(User $user): bool
    {
        return $user->can('clinic.access', ['product', 'r']);
    }

    public function create(User $user): bool
    {
        return $user->can('clinic.access', ['product', 'w']);
    }

    public function update(User $user): bool
    {
        return $user->can('clinic.access', ['product', 'w']);
    }

    public function delete(User $user): bool
    {
        return $user->can('clinic.access', ['product', 'w']);
    }
}
