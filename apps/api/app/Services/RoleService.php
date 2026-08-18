<?php

namespace App\Services;

use App\Actions\Role\ResetRolePermissionsAction;
use App\Actions\Role\UpdateRolePermissionsAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Use case pengaturan izin peran klinik.
 */
class RoleService
{
    /**
     * @param  array<string, array{view: bool, manage: bool}>  $modules
     */
    public function updatePermissions(User $actor, string $roleName, array $modules): Role
    {
        return DB::transaction(
            fn () => app(UpdateRolePermissionsAction::class)->handle($actor, $roleName, $modules),
        );
    }

    public function resetPermissions(User $actor, string $roleName): Role
    {
        return DB::transaction(
            fn () => app(ResetRolePermissionsAction::class)->handle($actor, $roleName),
        );
    }
}
