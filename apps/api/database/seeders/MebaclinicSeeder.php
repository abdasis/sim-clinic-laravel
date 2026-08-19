<?php

namespace Database\Seeders;

use App\Actions\Tenant\SyncTenantClinicRolesAction;
use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Satu-satunya tenant klinik yang disemai (spec 002, T014): Mebaclinic,
 * lengkap dengan 4 staf (1 per peran), beberapa pasien, plus katalog layanan
 * dan produk final.
 *
 * Sebelumnya bernama ClinicDemoSeeder dan menumbuhkan tenant `demo` di samping
 * `klinik-sehat`. Keduanya dihapus: yang tersisa hanya `central` dan tenant ini.
 */
class MebaclinicSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'mebaclinic'],
            ['name' => 'Mebaclinic', 'phone' => '081234567890', 'status' => 'active'],
        );

        app()->instance('tenant', $tenant);

        app(SyncTenantClinicRolesAction::class)->handle($tenant->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $staff = [
            ['name' => 'Admin Klinik', 'email' => 'admin@mebaclinic.test', 'clinic_role' => ClinicRole::Admin],
            ['name' => 'dr. Sari', 'email' => 'dokter@mebaclinic.test', 'clinic_role' => ClinicRole::Doctor],
            ['name' => 'Terapis Ratna', 'email' => 'terapis@mebaclinic.test', 'clinic_role' => ClinicRole::Therapist],
            ['name' => 'Kasir Dewi', 'email' => 'kasir@mebaclinic.test', 'clinic_role' => ClinicRole::Cashier],
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
            ['name' => 'Ani Wijaya', 'whatsapp' => '081200000001', 'gender' => 'female'],
            ['name' => 'Budi Santoso', 'whatsapp' => '081200000002', 'gender' => 'male'],
        ];
        foreach ($patients as $p) {
            Patient::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'whatsapp' => $p['whatsapp']],
                ['name' => $p['name'], 'gender' => $p['gender']],
            );
        }

        // Katalog layanan memakai pricelist final MEBA Clinic; seeder ini
        // tidak lagi membuat treatment contoh sendiri supaya seed ulang tidak
        // memunculkan kembali layanan percobaan.
        app(MebaServiceSeeder::class)->seedTenant($tenant);

        // Katalog produk memakai daftar final MEBA Clinic; seeder ini tidak
        // lagi membuat produk contoh sendiri supaya seed ulang tidak
        // memunculkan kembali produk percobaan.
        app(MebaProductSeeder::class)->seedTenant($tenant);

        app()->forgetInstance('tenant');
    }
}
