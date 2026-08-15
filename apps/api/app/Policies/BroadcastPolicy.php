<?php

namespace App\Policies;

use App\Models\User;

/**
 * Broadcast menjangkau pasien langsung, jadi hanya admin (R2).
 */
class BroadcastPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('broadcast.view');
    }

    public function view(User $user): bool
    {
        return $user->can('broadcast.view');
    }

    public function create(User $user): bool
    {
        return $user->can('broadcast.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('broadcast.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('broadcast.manage');
    }
}
