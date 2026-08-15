<?php

namespace Database\Seeders;

use App\Actions\Tenant\SeedDefaultCommissionRulesAction;
use App\Actions\Tenant\SyncTenantClinicRolesAction;
use App\Enums\UserRole;
use App\Http\Middleware\SetPermissionTeamId;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seed permission per modul, role platform global (team null), dan role
 * klinik per tenant. Matriks peran klinik mengacu ke SyncTenantClinicRolesAction::MATRIX
 * agar tidak ada duplikasi sumber kebenaran.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $this->seedPermissions();
        $this->seedGlobalRoles($registrar);
        $this->seedClinicRolesForExistingTenants();

        $registrar->forgetCachedPermissions();
    }

    /**
     * Setiap modul punya permission baca dan tulis: {module}.view / {module}.manage.
     */
    private function seedPermissions(): void
    {
        foreach ($this->modules() as $module) {
            foreach (['view', 'manage'] as $ability) {
                Permission::findOrCreate("{$module}.{$ability}", SyncTenantClinicRolesAction::GUARD);
            }
        }
    }

    /**
     * Role platform dipakai lintas tenant lewat team sentinel platform.
     */
    private function seedGlobalRoles(PermissionRegistrar $registrar): void
    {
        $registrar->setPermissionsTeamId(SetPermissionTeamId::PLATFORM_TEAM_ID);

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, SyncTenantClinicRolesAction::GUARD);
        }
    }

    private function seedClinicRolesForExistingTenants(): void
    {
        $action = app(SyncTenantClinicRolesAction::class);

        Tenant::query()->each(function (Tenant $tenant) use ($action): void {
            $action->handle($tenant->id);
            app(SeedDefaultCommissionRulesAction::class)->handle($tenant->id);
        });
    }

    /**
     * @return array<int, string>
     */
    private function modules(): array
    {
        $modules = [];

        foreach (SyncTenantClinicRolesAction::MATRIX as $byModule) {
            $modules = array_merge($modules, array_keys($byModule));
        }

        return array_values(array_unique($modules));
    }
}
