<?php

namespace App\Policies;

use App\Models\User;

/**
 * StockMovement: Admin = catat/lihat pergerakan stok (R2 matriks, modul inventory).
 */
class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clinic.access', ['inventory', 'r']);
    }

    public function view(User $user): bool
    {
        return $user->can('clinic.access', ['inventory', 'r']);
    }

    public function create(User $user): bool
    {
        return $user->can('clinic.access', ['inventory', 'w']);
    }

    public function update(User $user): bool
    {
        return $user->can('clinic.access', ['inventory', 'w']);
    }

    public function delete(User $user): bool
    {
        return $user->can('clinic.access', ['inventory', 'w']);
    }
}
