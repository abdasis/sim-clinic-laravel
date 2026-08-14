<?php

namespace App\Policies;

use App\Models\User;

/**
 * Service: Admin = CRUD; Dokter/Terapis = view only; Kasir = tidak ada (R2).
 */
class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('service.view');
    }

    public function view(User $user): bool
    {
        return $user->can('service.view');
    }

    public function create(User $user): bool
    {
        return $user->can('service.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('service.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('service.manage');
    }
}
