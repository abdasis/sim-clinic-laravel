<?php

use App\Actions\Tenant\SyncTenantClinicRolesAction;
use App\Http\Middleware\SetPermissionTeamId;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lengkapi izin peran klinik yang belum pernah sampai ke server.
 *
 * Izin dibuat oleh RolesAndPermissionsSeeder, sementara deploy hanya
 * menjalankan migrate. Modul yang lahir setelah seeder terakhir dijalankan —
 * `category`, misalnya — permissionnya tidak pernah ada di database produksi,
 * sehingga setiap permintaan ke modul itu ditolak 403 bahkan untuk admin
 * klinik. Di halaman kelola kategori, penolakan itu tampil sebagai daftar yang
 * memuat terus.
 *
 * Sengaja aditif: hanya izin yang belum dimiliki yang ditambahkan. Klinik kini
 * boleh menyesuaikan izin perannya sendiri, dan syncPermissions di sini akan
 * mengembalikan semuanya ke bawaan — menghapus penyesuaian yang tidak diminta
 * siapa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        // Permission bersifat global (tanpa team); rolenya yang per klinik.
        $registrar->setPermissionsTeamId(SetPermissionTeamId::PLATFORM_TEAM_ID);

        foreach (SyncTenantClinicRolesAction::modules() as $module) {
            foreach (['view', 'manage'] as $ability) {
                Permission::findOrCreate("{$module}.{$ability}", SyncTenantClinicRolesAction::GUARD);
            }
        }

        foreach ($this->tenantIds() as $tenantId) {
            $registrar->setPermissionsTeamId($tenantId);

            foreach (SyncTenantClinicRolesAction::MATRIX as $roleName => $modules) {
                $role = Role::where('name', $roleName)
                    ->where('guard_name', SyncTenantClinicRolesAction::GUARD)
                    ->first();

                $role?->givePermissionTo(
                    SyncTenantClinicRolesAction::permissionNamesFor($modules),
                );
            }
        }

        $registrar->setPermissionsTeamId($previous);
        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Tidak dibatalkan: mencabut izin di sini akan ikut mencabut yang
        // memang sudah dimiliki klinik sejak sebelum migrasi ini.
    }

    /** @return array<int, int> */
    private function tenantIds(): array
    {
        return DB::table('tenants')->pluck('id')->all();
    }
};
