<?php

namespace Database\Seeders;

use App\Actions\Tenant\SyncTenantClinicRolesAction;
use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Data demo klinik (spec 002, T014): 1 tenant demo, 4 staf (1 per peran),
 * beberapa pasien, 3 layanan, dan katalog produk final.
 */
class ClinicDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Klinik Cantik Demo', 'phone' => '081234567890', 'status' => 'active'],
        );

        app()->instance('tenant', $tenant);

        app(SyncTenantClinicRolesAction::class)->handle($tenant->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $staff = [
            ['name' => 'Admin Klinik', 'email' => 'admin@demo.test', 'clinic_role' => ClinicRole::Admin],
            ['name' => 'dr. Sari', 'email' => 'dokter@demo.test', 'clinic_role' => ClinicRole::Doctor],
            ['name' => 'Terapis Ratna', 'email' => 'terapis@demo.test', 'clinic_role' => ClinicRole::Therapist],
            ['name' => 'Kasir Dewi', 'email' => 'kasir@demo.test', 'clinic_role' => ClinicRole::Cashier],
        ];

        foreach ($staff as $s) {
            $user = User::query()->firstOrCreate(
                ['email' => $s['email']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $s['name'],
                    'password' => Hash::make('password123'),
                    'role' => UserRole::Member,
                    'status' => UserStatus::Active,
                    'clinic_role' => $s['clinic_role'],
                ],
            );

            $user->syncRoles([$s['clinic_role']->value]);
        }

        $patients = [
            ['name' => 'Ani Wijaya', 'phone' => '081200000001', 'gender' => 'female'],
            ['name' => 'Budi Santoso', 'phone' => '081200000002', 'gender' => 'male'],
        ];
        foreach ($patients as $p) {
            Patient::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'phone' => $p['phone']],
                ['name' => $p['name'], 'gender' => $p['gender']],
            );
        }

        $services = [
            ['name' => 'Facial Basic', 'price' => 150000],
            ['name' => 'Chemical Peeling', 'price' => 350000],
            ['name' => 'Konsultasi Dokter', 'price' => 100000],
        ];
        foreach ($services as $s) {
            Service::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $s['name']],
                ['price' => $s['price'], 'status' => 'active'],
            );
        }

        // Katalog produk memakai daftar final MEBA Clinic; seeder demo tidak
        // lagi membuat produk contoh sendiri supaya seed ulang tidak
        // memunculkan kembali produk percobaan.
        app(MebaProductSeeder::class)->seedTenant($tenant);

        app()->forgetInstance('tenant');
    }
}
