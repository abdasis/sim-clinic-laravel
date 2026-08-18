<?php

namespace App\Actions\Role;

use App\Actions\LogAuditAction;
use App\Actions\Tenant\SyncTenantClinicRolesAction;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Kembalikan izin satu peran klinik ke template bawaan.
 */
class ResetRolePermissionsAction
{
    public function handle(User $actor, string $roleName): Role
    {
        $names = SyncTenantClinicRolesAction::permissionNamesFor(
            SyncTenantClinicRolesAction::MATRIX[$roleName] ?? [],
        );

        $this->guardSelfLockout($actor, $roleName, $names);

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId(app('tenant')->id);

        $role = Role::findOrCreate($roleName, SyncTenantClinicRolesAction::GUARD);
        $old = $role->permissions->pluck('name')->sort()->values()->all();

        $role->syncPermissions($names);

        $registrar->setPermissionsTeamId($previous);
        $registrar->forgetCachedPermissions();

        app(LogAuditAction::class)->handle(
            'role.permissions_reset',
            null,
            $actor,
            ['role' => $roleName, 'old' => $old, 'new' => collect($names)->sort()->values()->all()],
            'Mengembalikan izin peran '.$roleName.' ke pengaturan bawaan.',
        );

        return $role;
    }

    /**
     * Bawaan admin selalu memuat izin pengurus, jadi ini praktis tidak pernah
     * memicu. Tetap diperiksa supaya perubahan template kelak tidak diam-diam
     * mengunci admin dari layarnya sendiri.
     *
     * @param  array<int, string>  $names
     */
    private function guardSelfLockout(User $actor, string $roleName, array $names): void
    {
        if ($actor->clinic_role?->value !== $roleName) {
            return;
        }

        foreach (UpdateRolePermissionsAction::LOCKOUT_GUARDED as $guarded) {
            abort_if(! in_array($guarded, $names, true), 422, __('role.self_lockout'));
        }
    }
}
