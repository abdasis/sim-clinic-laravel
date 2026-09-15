<?php

namespace App\Policies;

use App\Models\User;

/**
 * Tingkat member: Admin = CRUD; Kasir = lihat saja, supaya potongan yang
 * muncul di layar bayar bisa ditelusuri sumbernya tanpa bisa diubah.
 */
class MembershipTierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('membership.view');
    }

    public function view(User $user): bool
    {
        return $user->can('membership.view');
    }

    public function create(User $user): bool
    {
        return $user->can('membership.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('membership.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('membership.manage');
    }
}
