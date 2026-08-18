<?php

use App\Actions\Tenant\SyncTenantClinicRolesAction;
use App\Http\Middleware\SetPermissionTeamId;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Modul izin baru: `role`, yang mengatur siapa boleh mengubah izin.
 *
 * Aditif dengan sengaja. Klinik yang sudah menyesuaikan izinnya cukup
 * mendapat tambahan grant untuk admin; `syncPermissions` di sini akan
 * mengembalikan semuanya ke bawaan dan menghapus penyesuaian itu.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['role.view', 'role.manage'];

    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        // Permission bersifat global (tanpa team); rolenya yang per klinik.
        $registrar->setPermissionsTeamId(SetPermissionTeamId::PLATFORM_TEAM_ID);

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, SyncTenantClinicRolesAction::GUARD);
        }

        foreach ($this->tenantIds() as $tenantId) {
            $registrar->setPermissionsTeamId($tenantId);

            $admin = Role::where('name', 'admin')
                ->where('guard_name', SyncTenantClinicRolesAction::GUARD)
                ->first();

            $admin?->givePermissionTo(self::PERMISSIONS);
        }

        $registrar->setPermissionsTeamId($previous);
        $registrar->forgetCachedPermissions();
    }

    /** @return array<int, int> */
    private function tenantIds(): array
    {
        return DB::table('tenants')->pluck('id')->all();
    }

    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);

        DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', SyncTenantClinicRolesAction::GUARD)
            ->delete();

        $registrar->forgetCachedPermissions();
    }
};
