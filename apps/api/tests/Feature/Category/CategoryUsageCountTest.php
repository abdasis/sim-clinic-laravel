<?php

namespace Tests\Feature\Category;

use App\Enums\ClinicRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Kolom "Dipakai" di halaman Kategori.
 *
 * Pivot kategori polimorfik dan tidak punya foreign key, jadi barisnya tidak
 * ikut hilang saat produk atau layanannya dihapus. Tanpa penanganan, klinik
 * yang baru saja mengosongkan katalognya tetap melihat kategori "dipakai" oleh
 * barang yang sudah lenyap.
 */
class CategoryUsageCountTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function category(string $name, string $type): Category
    {
        return Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'type' => $type,
            'status' => 'active',
        ]);
    }

    private function usageOf(string $name, string $type): int
    {
        $row = collect(
            $this->getJson($this->tenantUrl("categories?filter[type]={$type}&filter[status]=all"))
                ->assertOk()
                ->json('data')
        )->firstWhere('name', $name);

        return (int) $row['usage_count'];
    }

    public function test_deleting_a_product_frees_its_category(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $category = $this->category('Serum', 'product');

        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $product->syncCategory($category->id);

        $this->assertSame(1, $this->usageOf('Serum', 'product'));

        $this->deleteJson($this->tenantUrl("products/{$product->id}/force"))->assertOk();

        $this->assertSame(0, $this->usageOf('Serum', 'product'));
        $this->assertDatabaseMissing('categorizables', ['categorizable_id' => $product->id]);
    }

    public function test_deleting_a_service_frees_its_category(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $category = $this->category('Skinbooster', 'service');

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $service->syncCategory($category->id);

        $this->assertSame(1, $this->usageOf('Skinbooster', 'service'));

        $this->deleteJson($this->tenantUrl("services/{$service->id}/force"))->assertOk();

        $this->assertSame(0, $this->usageOf('Skinbooster', 'service'));
    }

    public function test_archiving_keeps_the_category_attached(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $category = $this->category('Toner', 'product');

        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $product->syncCategory($category->id);

        // Diarsipkan, bukan dihapus: barangnya masih ada dan masih berkategori.
        $this->deleteJson($this->tenantUrl("products/{$product->id}"))->assertOk();

        $this->assertSame(1, $this->usageOf('Toner', 'product'));
    }

    public function test_the_purge_migration_clears_counts_left_by_old_deletions(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $category = $this->category('Night Cream', 'product');

        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $product->syncCategory($category->id);

        // Keadaan di server: produknya sudah dihapus sebelum pelepasan
        // kategori ada, jadi baris pivotnya tertinggal.
        DB::table('products')->where('id', $product->id)->delete();

        $this->assertSame(1, $this->usageOf('Night Cream', 'product'));

        (require database_path('migrations/2026_08_19_020000_purge_orphan_categorizables.php'))->up();

        $this->assertSame(0, $this->usageOf('Night Cream', 'product'));
    }

    public function test_the_purge_leaves_categories_that_are_really_used(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $category = $this->category('Facial Wash', 'product');

        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $product->syncCategory($category->id);

        (require database_path('migrations/2026_08_19_020000_purge_orphan_categorizables.php'))->up();

        $this->assertSame(1, $this->usageOf('Facial Wash', 'product'));
    }
}
