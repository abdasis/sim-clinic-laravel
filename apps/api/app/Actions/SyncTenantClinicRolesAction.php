<?php

namespace App\Actions;

use App\Services\ClinicPermission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Buat/selaraskan role klinik milik satu tenant beserta permission-nya
 * sesuai ClinicPermission::MATRIX. Dipakai saat tenant baru dibuat dan
 * saat seeding.
 */
class SyncTenantClinicRolesAction
{
    public const GUARD = 'sanctum';

    public function handle(int $tenantId): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($tenantId);

        foreach (ClinicPermission::MATRIX as $roleName => $modules) {
            Role::findOrCreate($roleName, self::GUARD)
                ->syncPermissions($this->permissionNamesFor($modules));
        }

        $registrar->setPermissionsTeamId($previous);
        $registrar->forgetCachedPermissions();
    }

    /**
     * @param  array<string, string>  $modules  module => 'r'|'rw'
     * @return array<int, string>
     */
    private function permissionNamesFor(array $modules): array
    {
        $names = [];

        foreach ($modules as $module => $access) {
            $names[] = "{$module}.view";

            if ($access === 'rw') {
                $names[] = "{$module}.manage";
            }
        }

        return $names;
    }
}
