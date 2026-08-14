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
        return $user->can('inventory.view');
    }

    public function view(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('inventory.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('inventory.manage');
    }
}
