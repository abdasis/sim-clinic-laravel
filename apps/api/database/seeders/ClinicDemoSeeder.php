<?php

namespace Database\Seeders;

use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data demo klinik (spec 002, T014): 1 tenant demo, 4 staf (1 per peran),
 * beberapa pasien, 3 layanan, 2 produk.
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

        $staff = [
            ['name' => 'Admin Klinik', 'email' => 'admin@demo.test', 'clinic_role' => ClinicRole::Admin],
            ['name' => 'dr. Sari', 'email' => 'dokter@demo.test', 'clinic_role' => ClinicRole::Doctor],
            ['name' => 'Terapis Ratna', 'email' => 'terapis@demo.test', 'clinic_role' => ClinicRole::Therapist],
            ['name' => 'Kasir Dewi', 'email' => 'kasir@demo.test', 'clinic_role' => ClinicRole::Cashier],
        ];

        foreach ($staff as $s) {
            User::query()->firstOrCreate(
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

        $products = [
            ['name' => 'Serum Vitamin C', 'unit' => 'botol', 'stock_balance' => 20, 'min_threshold' => 5, 'price' => 120000],
            ['name' => 'Sunscreen SPF50', 'unit' => 'pcs', 'stock_balance' => 15, 'min_threshold' => 5, 'price' => 90000],
        ];
        foreach ($products as $p) {
            Product::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $p['name']],
                [
                    'unit' => $p['unit'],
                    'stock_balance' => $p['stock_balance'],
                    'min_threshold' => $p['min_threshold'],
                    'price' => $p['price'],
                    'status' => 'active',
                ],
            );
        }

        app()->forgetInstance('tenant');
    }
}
