<?php

namespace App\Policies;

use App\Models\User;

/**
 * Promo: Admin = CRUD; Kasir = lihat saja supaya harga di kasir bisa
 * ditelusuri sumbernya; peran lain tidak berkepentingan (R2).
 */
class PromoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('promo.view');
    }

    public function view(User $user): bool
    {
        return $user->can('promo.view');
    }

    public function create(User $user): bool
    {
        return $user->can('promo.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('promo.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('promo.manage');
    }
}
