<?php

namespace Database\Seeders;

use App\Actions\SyncTenantClinicRolesAction;
use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Middleware\SetPermissionTeamId;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tenant demo + tenant_admin (spec 001, US1).
 */
class TenantAdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $tenant = Tenant::query()->firstOrCreate(
                ['slug' => 'klinik-sehat'],
                ['name' => 'Klinik Sehat', 'phone' => '081200000000', 'status' => 'active'],
            );

            app(SyncTenantClinicRolesAction::class)->handle($tenant->id);

            $admin = User::query()->firstOrCreate(
                ['email' => 'admin@klinik-sehat.test'],
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'Admin Klinik Sehat',
                    'password' => Hash::make('password123'),
                    'role' => UserRole::TenantAdmin,
                    'status' => UserStatus::Active,
                    'clinic_role' => ClinicRole::Admin,
                ],
            );

            $registrar = app(PermissionRegistrar::class);

            $registrar->setPermissionsTeamId(SetPermissionTeamId::PLATFORM_TEAM_ID);
            $admin->assignRole(UserRole::TenantAdmin->value);

            $registrar->setPermissionsTeamId($tenant->id);
            $admin->assignRole(ClinicRole::Admin->value);
        });
    }
}
