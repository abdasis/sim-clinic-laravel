<?php

namespace Database\Seeders;

use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Tenant demo + tenant_admin (spec 001, US1).
 */
class TenantAdminSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'klinik-sehat'],
            ['name' => 'Klinik Sehat', 'phone' => '081200000000', 'status' => 'active'],
        );

        User::query()->firstOrCreate(
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
    }
}
