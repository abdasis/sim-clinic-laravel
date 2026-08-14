<?php

namespace App\Policies;

use App\Models\User;

/**
 * Staff: hanya Admin klinik yang boleh kelola staf (R2 matriks, FR-002, FR-003).
 * Otorisasi didelegasikan ke permission spatie per tenant (team context
 * dipasang SetPermissionTeamId), yang di-generate dari SyncTenantClinicRolesAction::MATRIX.
 */
class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('staff.view');
    }

    public function view(User $user): bool
    {
        return $user->can('staff.view');
    }

    public function create(User $user): bool
    {
        return $user->can('staff.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('staff.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('staff.manage');
    }

    public function deactivate(User $actor): bool
    {
        return $actor->can('staff.manage');
    }
}
