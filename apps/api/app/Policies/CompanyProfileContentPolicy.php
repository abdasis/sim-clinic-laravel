<?php

namespace App\Policies;

use App\Models\User;

/**
 * Konten company profile: Admin = CRUD; peran klinik lain tidak punya akses
 * (modul `content` hanya diberikan ke admin di matriks peran).
 */
class CompanyProfileContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.view');
    }

    public function view(User $user): bool
    {
        return $user->can('content.view');
    }

    public function create(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('content.manage');
    }
}
