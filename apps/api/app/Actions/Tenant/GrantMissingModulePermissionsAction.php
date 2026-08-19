<?php

namespace App\Actions\Tenant;

use App\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lengkapi izin peran klinik yang tertinggal dari matriks bawaan.
 *
 * Izin peran dipasang sekali saat kliniknya dibuat dan tidak pernah ditengok
 * lagi. Modul yang ditambahkan sesudah itu — kategori, izin peran, dan
 * sebelumnya pengeluaran — tidak pernah sampai ke klinik yang sudah berjalan:
 * deploy hanya menjalankan migrasi, sementara yang membuat izinnya adalah
 * seeder. Yang terlihat di layar cuma "Anda tidak memiliki izin untuk
 * mengakses modul ini" di halaman yang menurut sidebar boleh dibuka.
 *
 * Sengaja hanya menambah, tidak pernah mencabut. Sejak izin bisa diatur per
 * klinik, mencabut berarti membatalkan pengaturan yang sengaja dibuat admin —
 * yang dikejar di sini cuma modul yang belum pernah sampai.
 */
class GrantMissingModulePermissionsAction
{
    /**
     * @return array<string, int> nama peran => jumlah izin yang ditambahkan
     */
    public function handle(int $tenantId): array
    {
        $this->ensurePermissionsExist();

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($tenantId);

        $added = [];

        foreach (SyncTenantClinicRolesAction::MATRIX as $roleName => $modules) {
            $role = Role::findOrCreate($roleName, SyncTenantClinicRolesAction::GUARD);

            $missing = array_values(array_diff(
                SyncTenantClinicRolesAction::permissionNamesFor($modules),
                $role->permissions->pluck('name')->all(),
            ));

            if ($missing !== []) {
                $role->givePermissionTo($missing);
                $added[$roleName] = count($missing);
            }
        }

        $registrar->setPermissionsTeamId($previous);
        $registrar->forgetCachedPermissions();

        return $added;
    }

    /**
     * @return array<int, array{tenant: Tenant, added: array<string, int>}>
     */
    public function forAllTenants(): array
    {
        $result = [];

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use (&$result): void {
            $added = $this->handle($tenant->id);

            if ($added !== []) {
                $result[] = ['tenant' => $tenant, 'added' => $added];
            }
        });

        return $result;
    }

    /**
     * Baris izinnya sendiri global, bukan per klinik. Tanpa ini
     * `givePermissionTo` melempar untuk modul yang seeder-nya belum pernah
     * dijalankan di server.
     */
    private function ensurePermissionsExist(): void
    {
        foreach (SyncTenantClinicRolesAction::modules() as $module) {
            foreach (['view', 'manage'] as $ability) {
                Permission::findOrCreate("{$module}.{$ability}", SyncTenantClinicRolesAction::GUARD);
            }
        }
    }
}
