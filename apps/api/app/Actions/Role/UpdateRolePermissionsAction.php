<?php

namespace App\Actions\Role;

use App\Actions\LogAuditAction;
use App\Actions\Tenant\SyncTenantClinicRolesAction;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Simpan izin satu peran klinik.
 *
 * Yang berlaku saat berjalan adalah isi tabel, bukan MATRIX di kode; peta itu
 * tinggal template bawaan.
 */
class UpdateRolePermissionsAction
{
    /**
     * Izin yang, kalau dicabut dari peran sendiri, membuat admin tidak bisa
     * mengembalikannya lagi lewat layar mana pun.
     */
    public const LOCKOUT_GUARDED = ['role.manage', 'staff.manage'];

    /**
     * @param  array<string, array{view: bool, manage: bool}>  $modules
     */
    public function handle(User $actor, string $roleName, array $modules): Role
    {
        $names = $this->permissionNames($modules);

        $this->guardSelfLockout($actor, $roleName, $names);

        $registrar = app(PermissionRegistrar::class);
        $tenantId = app('tenant')->id;
        $previous = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($tenantId);

        $role = Role::findOrCreate($roleName, SyncTenantClinicRolesAction::GUARD);
        $old = $role->permissions->pluck('name')->sort()->values()->all();

        $role->syncPermissions($names);

        $registrar->setPermissionsTeamId($previous);
        $registrar->forgetCachedPermissions();

        $new = collect($names)->sort()->values()->all();

        app(LogAuditAction::class)->handle(
            'role.permissions_changed',
            null,
            $actor,
            ['role' => $roleName, 'old' => $old, 'new' => $new],
            'Mengubah izin peran '.$roleName.': '.count($new).' izin aktif (sebelumnya '.count($old).').',
        );

        return $role;
    }

    /**
     * @param  array<string, array{view: bool, manage: bool}>  $modules
     * @return array<int, string>
     */
    private function permissionNames(array $modules): array
    {
        $names = [];

        foreach ($modules as $module => $access) {
            if (! empty($access['view'])) {
                $names[] = $module.'.view';
            }

            if (! empty($access['manage'])) {
                $names[] = $module.'.manage';
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Mencabut izin pengurus dari peran sendiri berarti tidak ada lagi jalan
     * kembali — layarnya ikut tertutup bersama izinnya.
     *
     * @param  array<int, string>  $names
     */
    private function guardSelfLockout(User $actor, string $roleName, array $names): void
    {
        if ($actor->clinic_role?->value !== $roleName) {
            return;
        }

        foreach (self::LOCKOUT_GUARDED as $guarded) {
            abort_if(! in_array($guarded, $names, true), 422, __('role.self_lockout'));
        }
    }
}
