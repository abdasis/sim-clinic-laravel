<?php

namespace App\Actions\Tenant;

use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Buat/selaraskan role klinik milik satu tenant beserta permission-nya.
 * Dipakai saat tenant baru dibuat dan saat seeding.
 */
class SyncTenantClinicRolesAction
{
    public const GUARD = 'sanctum';

    /**
     * Sumber tunggal peran klinik → modul (spec 002, R2 matriks).
     * 'r' = hanya baca ({module}.view), 'rw' = baca + kelola ({module}.manage).
     *
     * Menambah modul cukup di sini; permission dan role ikut tersinkron
     * lewat RolesAndPermissionsSeeder.
     *
     * @var array<string, array<string, string>> role => [module => 'r'|'rw']
     */
    public const MATRIX = [
        'admin' => [
            'staff' => 'rw', 'service' => 'rw', 'patient' => 'rw', 'booking' => 'rw',
            'medical_record' => 'rw', 'product' => 'rw', 'inventory' => 'rw',
            'transaction' => 'rw', 'invoice' => 'rw', 'report' => 'rw',
            'content' => 'rw',
        ],
        'doctor' => [
            'patient' => 'rw', 'booking' => 'rw', 'medical_record' => 'rw', 'service' => 'r',
        ],
        'therapist' => [
            'patient' => 'r', 'booking' => 'rw', 'medical_record' => 'rw', 'service' => 'r',
        ],
        'cashier' => [
            'patient' => 'rw', 'transaction' => 'rw', 'invoice' => 'rw',
        ],
    ];

    public function handle(int $tenantId): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($tenantId);

        foreach (self::MATRIX as $roleName => $modules) {
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
