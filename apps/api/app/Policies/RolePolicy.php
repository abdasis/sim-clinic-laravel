<?php

namespace App\Policies;

use App\Models\User;

/**
 * Pengaturan izin peran klinik. Hanya peran yang punya `role.manage` yang
 * boleh mengubahnya — termasuk mengubah izin perannya sendiri, dengan
 * penjagaan terpisah supaya tidak mengunci diri sendiri.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('role.view');
    }

    public function view(User $user): bool
    {
        return $user->can('role.view');
    }

    public function update(User $user): bool
    {
        return $user->can('role.manage');
    }

    public function reset(User $user): bool
    {
        return $user->can('role.manage');
    }
}
