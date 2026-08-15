<?php

namespace Tests\Feature\CompanyProfile;

use App\Models\Tenant;
use Database\Seeders\CompanyProfileDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Seeder demo dipakai untuk menilai tampilan landing tanpa mengisi CMS dari
 * nol, jadi ia harus mengisi seluruh section dan bisa dijalankan berulang
 * tanpa menggandakan data.
 *
 * Jumlah baris sengaja tidak dipatok: isi demo sering diganti, dan yang
 * dijaga di sini perilakunya — bukan berapa banyak contoh yang ditulis.
 */
class CompanyProfileDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private const CONTENT_TABLES = [
        'company_profile_slides',
        'company_value_props',
        'company_treatments',
        'company_brands',
        'company_testimonials',
        'company_content_sections',
        'company_navigation_items',
    ];

    public function test_seeder_fills_every_landing_section(): void
    {
        $tenant = $this->makeTenant();

        $this->seed(CompanyProfileDemoSeeder::class);

        $this->assertDatabaseHas('company_profile_settings', [
            'tenant_id' => $tenant->id,
            'is_published' => true,
        ]);

        foreach (self::CONTENT_TABLES as $table) {
            $this->assertGreaterThan(
                0,
                DB::table($table)->count(),
                "Section {$table} tidak terisi seeder demo.",
            );
        }
    }

    public function test_seeder_can_run_twice_without_duplicating(): void
    {
        $this->makeTenant();

        $this->seed(CompanyProfileDemoSeeder::class);
        $afterFirst = $this->contentCounts();

        $this->seed(CompanyProfileDemoSeeder::class);

        $this->assertSame(
            $afterFirst,
            $this->contentCounts(),
            'Menjalankan seeder dua kali tidak boleh menambah baris.',
        );
    }

    public function test_seeder_covers_every_active_clinic_tenant(): void
    {
        $first = $this->makeTenant('klinik-satu');
        $second = $this->makeTenant('klinik-dua');

        $this->seed(CompanyProfileDemoSeeder::class);

        $this->assertDatabaseHas('company_profile_settings', ['tenant_id' => $first->id]);
        $this->assertDatabaseHas('company_profile_settings', ['tenant_id' => $second->id]);
    }

    public function test_seeder_is_skipped_when_there_is_no_clinic_tenant(): void
    {
        $this->seed(CompanyProfileDemoSeeder::class);

        $this->assertDatabaseCount('company_profile_settings', 0);
    }

    private function makeTenant(string $slug = 'demo'): Tenant
    {
        return Tenant::create([
            'name' => 'Klinik '.$slug,
            'slug' => $slug,
            'phone' => '081234567890',
            'status' => 'active',
        ]);
    }

    /** @return array<string, int> */
    private function contentCounts(): array
    {
        $counts = [];

        foreach (self::CONTENT_TABLES as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}
