<?php

namespace Tests\Feature\CompanyProfile;

use App\Models\Tenant;
use Database\Seeders\CompanyProfileDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seeder demo dipakai untuk menilai tampilan landing tanpa mengisi CMS dari
 * nol, jadi ia harus bisa dijalankan berulang tanpa menggandakan data.
 */
class CompanyProfileDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_fills_the_demo_tenant_landing(): void
    {
        $tenant = Tenant::create([
            'name' => 'Klinik Cantik Demo',
            'slug' => 'demo',
            'phone' => '081234567890',
            'status' => 'active',
        ]);

        $this->seed(CompanyProfileDemoSeeder::class);

        $this->assertDatabaseHas('company_profile_settings', [
            'tenant_id' => $tenant->id,
            'is_published' => true,
        ]);
        $this->assertDatabaseCount('company_profile_slides', 2);
        $this->assertDatabaseCount('company_value_props', 3);
        $this->assertDatabaseCount('company_treatments', 3);
        $this->assertDatabaseCount('company_content_sections', 3);
        $this->assertDatabaseCount('company_navigation_items', 6);
    }

    public function test_seeder_can_run_twice_without_duplicating(): void
    {
        Tenant::create([
            'name' => 'Klinik Cantik Demo',
            'slug' => 'demo',
            'phone' => '081234567890',
            'status' => 'active',
        ]);

        $this->seed(CompanyProfileDemoSeeder::class);
        $this->seed(CompanyProfileDemoSeeder::class);

        $this->assertDatabaseCount('company_treatments', 3);
        $this->assertDatabaseCount('company_brands', 3);
        $this->assertDatabaseCount('company_testimonials', 2);
    }

    public function test_seeder_is_skipped_when_the_demo_tenant_is_absent(): void
    {
        $this->seed(CompanyProfileDemoSeeder::class);

        $this->assertDatabaseCount('company_profile_settings', 0);
    }
}
