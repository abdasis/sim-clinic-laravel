<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CentralTenantSeeder::class,
            TenantAdminSeeder::class,
            // Katalog produk disemai dari ClinicDemoSeeder karena terikat pada
            // satu tenant klinik, bukan data platform yang berlaku umum.
            ClinicDemoSeeder::class,
            CompanyProfileDemoSeeder::class,
        ]);
    }
}
