<?php

namespace Tests\Feature\Product;

use App\Enums\ServiceStatus;
use App\Models\Activity;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStockBalanceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Klinik Uji',
            'slug' => 'klinik-uji',
            'phone' => '0811',
            'status' => 'active',
        ]);

        app()->instance('tenant', $this->tenant);
    }

    public function test_stock_balance_is_ignored_on_create(): void
    {
        $product = app(ProductService::class)->create([
            'name' => 'Serum Vitamin C',
            'unit' => 'botol',
            'min_threshold' => 5,
            'price' => 150000,
            'stock_balance' => 999,
        ]);

        $this->assertSame(0, $product->fresh()->stock_balance, 'Saldo hanya boleh tumbuh lewat mutasi stok.');
    }

    public function test_stock_balance_is_ignored_on_update(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_balance' => 12,
        ]);

        app(ProductService::class)->update($product, [
            'name' => 'Nama Baru',
            'stock_balance' => 999,
        ]);

        $this->assertSame(12, $product->fresh()->stock_balance);
        $this->assertSame('Nama Baru', $product->fresh()->name);
    }

    public function test_archive_sets_status_and_logs_narrative(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Masker Wajah',
        ]);

        app(ProductService::class)->archive($product);

        $this->assertSame(ServiceStatus::Archived, $product->fresh()->status);

        $activity = Activity::where('event', 'product.archived')->latest('id')->first();
        $this->assertNotNull($activity);
        $this->assertSame('Mengarsipkan produk Masker Wajah.', $activity->description);
        $this->assertSame('active', $activity->properties['old']['status']);
        $this->assertSame('archived', $activity->properties['new']['status']);
    }

    public function test_create_logs_narrative_audit(): void
    {
        app(ProductService::class)->create([
            'name' => 'Toner Herbal',
            'unit' => 'botol',
            'min_threshold' => 3,
            'price' => 90000,
        ]);

        $activity = Activity::where('event', 'product.created')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('Membuat produk Toner Herbal.', $activity->description);
        $this->assertSame('Toner Herbal', $activity->properties['attributes']['name']);
    }
}
