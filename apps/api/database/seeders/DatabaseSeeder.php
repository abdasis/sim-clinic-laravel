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
            // Hanya satu tenant klinik: Mebaclinic. Katalog layanan dan produk
            // disemai dari sini karena terikat pada tenant itu, bukan data
            // platform yang berlaku umum.
            MebaclinicSeeder::class,
            CompanyProfileDemoSeeder::class,
        ]);
    }
}
