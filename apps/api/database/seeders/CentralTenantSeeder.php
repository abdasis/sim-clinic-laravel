<?php

namespace Database\Seeders;

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
 * Tenant central + admin platform (spec 001, T020).
 */
class CentralTenantSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $tenant = Tenant::query()->firstOrCreate(
                ['slug' => 'central'],
                ['name' => 'Central', 'phone' => '0000000000', 'status' => 'active'],
            );

            $admin = User::query()->firstOrCreate(
                ['email' => 'admin@platform.test'],
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'Platform Admin',
                    'password' => Hash::make('password123'),
                    'role' => UserRole::PlatformAdmin,
                    'status' => UserStatus::Active,
                ],
            );

            // Role platform berlaku lintas tenant (team sentinel platform).
            app(PermissionRegistrar::class)->setPermissionsTeamId(SetPermissionTeamId::PLATFORM_TEAM_ID);
            $admin->assignRole(UserRole::PlatformAdmin->value);
        });
    }
}
