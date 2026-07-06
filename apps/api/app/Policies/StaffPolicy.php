<?php

namespace App\Policies;

use App\Models\User;

/**
 * Staff: hanya Admin klinik yang boleh kelola staf (R2 matriks, FR-002, FR-003).
 */
class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clinic.access', ['staff', 'r']);
    }

    public function view(User $user): bool
    {
        return $user->can('clinic.access', ['staff', 'r']);
    }

    public function create(User $user): bool
    {
        return $user->can('clinic.access', ['staff', 'w']);
    }

    public function update(User $user): bool
    {
        return $user->can('clinic.access', ['staff', 'w']);
    }

    public function delete(User $user): bool
    {
        return $user->can('clinic.access', ['staff', 'w']);
    }

    public function deactivate(User $actor): bool
    {
        return $actor->can('clinic.access', ['staff', 'w']);
    }
}
