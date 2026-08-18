<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Migrasi backfill hanya berjalan sekali, di basis data sungguhan berisi data
 * klinik. Kalau salah, kategori seluruh katalog hilang dan tidak ada yang tahu
 * sampai ada yang membuka daftarnya.
 *
 * Migrasinya dipanggil langsung di sini: baris warisan disiapkan dulu dengan
 * kolom enum lama terisi, persis seperti keadaan sebelum rilis ini.
 */
class CategoryBackfillTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function runBackfill(): void
    {
        $migration = require database_path(
            'migrations/2026_08_18_120100_backfill_categories_from_enum_columns.php',
        );

        $migration->up();
    }

    /** Kosongkan hasil migrasi yang sudah berjalan saat menyiapkan basis data uji. */
    private function resetCategories(): void
    {
        DB::table('categorizables')->delete();
        DB::table('categories')->delete();
    }

    /** Barisnya ditulis lewat query builder supaya kolom enum lama ikut terisi. */
    private function legacyService(Tenant $tenant, string $name, string $category): int
    {
        return DB::table('services')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'category' => $category,
            'price' => 100000,
            'duration_minutes' => 60,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_enum_values_become_categories_and_are_attached(): void
    {
        $this->actingAsClinicUser();
        $this->resetCategories();

        $a = $this->legacyService($this->tenant, 'Glow Booster', 'skinbooster');
        $b = $this->legacyService($this->tenant, 'Vitamin Infusion', 'skinbooster');
        $c = $this->legacyService($this->tenant, 'Facial Basic', 'facial_peeling');

        $this->runBackfill();

        // Dua layanan dengan enum sama berbagi satu kategori, bukan dua.
        $this->assertSame(2, Category::query()->count());

        $skinbooster = Category::query()->where('name', 'Skinbooster')->first();
        $this->assertNotNull($skinbooster);
        $this->assertSame('service', $skinbooster->type->value);

        $this->assertSame('Skinbooster', Service::find($a)->assignedCategory()?->name);
        $this->assertSame('Skinbooster', Service::find($b)->assignedCategory()?->name);
        $this->assertSame('Facial & Peeling', Service::find($c)->assignedCategory()?->name);
    }

    /** Nama kategorinya memakai label yang selama ini dibaca admin, bukan slug. */
    public function test_names_use_the_labels_operators_already_know(): void
    {
        $this->actingAsClinicUser();
        $this->resetCategories();

        $this->legacyService($this->tenant, 'Treatment Lain', 'other');

        $this->runBackfill();

        $this->assertSame('Treatment Lainnya', Category::query()->first()->name);
    }

    /** Nilai enum di luar daftar tetap terbawa, tidak hilang diam-diam. */
    public function test_unknown_enum_value_is_still_carried_over(): void
    {
        $this->actingAsClinicUser();
        $this->resetCategories();

        $id = $this->legacyService($this->tenant, 'Layanan Aneh', 'kategori_lama_aneh');

        $this->runBackfill();

        $this->assertSame('Kategori Lama Aneh', Service::find($id)->assignedCategory()?->name);
    }

    public function test_rows_without_a_category_are_left_alone(): void
    {
        $this->actingAsClinicUser();
        $this->resetCategories();

        $id = DB::table('services')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tanpa Kategori',
            'category' => null,
            'price' => 100000,
            'duration_minutes' => 60,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runBackfill();

        $this->assertSame(0, Category::query()->count());
        $this->assertNull(Service::find($id)->assignedCategory());
    }

    /**
     * Kategori dibuat per klinik. Kalau dibagi, mengubah nama kategori di satu
     * klinik akan mengubahnya di klinik lain juga.
     */
    public function test_each_clinic_gets_its_own_category_row(): void
    {
        $satu = $this->createTenant('klinik-satu');
        $dua = $this->createTenant('klinik-dua');

        $this->tenant = $satu;
        $this->actingAsClinicUser();
        $this->resetCategories();

        $a = $this->legacyService($satu, 'Glow Booster', 'skinbooster');
        $b = $this->legacyService($dua, 'Glow Booster', 'skinbooster');

        $this->runBackfill();

        $rows = Category::withoutGlobalScopes()->where('name', 'Skinbooster')->get();

        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(
            [$satu->id, $dua->id],
            $rows->pluck('tenant_id')->all(),
        );

        // Masing-masing menempel ke kategori kliniknya sendiri.
        $this->assertSame(
            $rows->firstWhere('tenant_id', $satu->id)->id,
            DB::table('categorizables')->where('categorizable_type', 'service')
                ->where('categorizable_id', $a)->value('category_id'),
        );
        $this->assertSame(
            $rows->firstWhere('tenant_id', $dua->id)->id,
            DB::table('categorizables')->where('categorizable_type', 'service')
                ->where('categorizable_id', $b)->value('category_id'),
        );
    }

    /** Produk ikut terbawa, dengan jenis kategorinya sendiri. */
    public function test_products_are_backfilled_with_the_product_type(): void
    {
        $this->actingAsClinicUser();
        $this->resetCategories();

        $id = DB::table('products')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Serum Vitamin C',
            'category' => 'serum',
            'type' => 'retail',
            'unit' => 'botol',
            'stock_balance' => 0,
            'min_threshold' => 1,
            'price' => 150000,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runBackfill();

        $category = Product::find($id)->assignedCategory();

        $this->assertSame('Serum', $category?->name);
        $this->assertSame('product', $category?->type->value);
    }

    /** Dijalankan dua kali tidak boleh menggandakan kategori maupun pivot. */
    public function test_running_it_twice_changes_nothing(): void
    {
        $this->actingAsClinicUser();
        $this->resetCategories();

        $this->legacyService($this->tenant, 'Glow Booster', 'skinbooster');

        $this->runBackfill();
        $this->runBackfill();

        $this->assertSame(1, Category::query()->count());
        $this->assertSame(1, DB::table('categorizables')->count());
    }
}
