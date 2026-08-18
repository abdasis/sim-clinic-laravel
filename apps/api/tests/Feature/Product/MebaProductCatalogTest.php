<?php

namespace Tests\Feature\Product;

use App\Enums\PaymentStatus;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Promo;
use App\Models\PromoItem;
use App\Models\Transaction;
use Database\Seeders\MebaProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Katalog produk final. Yang diuji bukan sekadar "20 baris masuk", melainkan
 * bahwa seed ulang tidak menggandakan, produk percobaan benar-benar lenyap
 * dari master, dan riwayat penjualan produk lama tetap utuh.
 */
class MebaProductCatalogTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const EXPECTED_TOTAL = 20;

    private function seedCatalog(): void
    {
        app(MebaProductSeeder::class)->seedTenant($this->tenant);
        app()->instance('tenant', $this->tenant);
    }

    public function test_seeds_exactly_twenty_products_with_categories(): void
    {
        $this->actingAsClinicUser();
        $this->seedCatalog();

        $this->assertSame(self::EXPECTED_TOTAL, Product::query()->count());

        $perCategory = Product::query()->get()
            ->groupBy(fn (Product $product) => $product->assignedCategory()?->name)
            ->map->count();

        $this->assertSame(3, $perCategory['Facial Wash']);
        $this->assertSame(2, $perCategory['Toner']);
        $this->assertSame(4, $perCategory['Sunscreen']);
        $this->assertSame(6, $perCategory['Serum']);
        $this->assertSame(5, $perCategory['Night Cream']);
    }

    public function test_prices_match_the_official_list(): void
    {
        $this->actingAsClinicUser();
        $this->seedCatalog();

        $expected = [
            'Radiant Face Wash' => 75_000,
            'Creamy Facial Foam' => 65_000,
            'UV Skin Tint Neutral' => 200_000,
            'Serum Skintastic Lightening Serum' => 350_000,
            'Acnemies Spot' => 95_000,
            'CyteaPro' => 350_000,
            'Vita B Gel' => 70_000,
        ];

        foreach ($expected as $name => $price) {
            $product = Product::query()->where('name', $name)->firstOrFail();

            $this->assertEqualsWithDelta($price, (float) $product->price, 0.01, $name);
            $this->assertSame('active', $product->status->value, $name);
        }
    }

    public function test_seeding_twice_does_not_duplicate(): void
    {
        $this->actingAsClinicUser();

        $this->seedCatalog();
        $this->seedCatalog();
        $this->seedCatalog();

        $this->assertSame(self::EXPECTED_TOTAL, Product::query()->count());
        $this->assertSame(1, Product::query()->where('name', 'Cleansing Toner')->count());
    }

    public function test_updates_price_of_existing_product_instead_of_duplicating(): void
    {
        $this->actingAsClinicUser();

        // Produk final yang sudah ada lebih dulu dengan harga lama.
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cleansing Toner',
            'unit' => 'botol',
            'price' => 10_000,
            'status' => 'active',
        ]);

        $this->seedCatalog();

        $product = Product::query()->where('name', 'Cleansing Toner')->firstOrFail();

        $this->assertSame(1, Product::query()->where('name', 'Cleansing Toner')->count());
        $this->assertEqualsWithDelta(65_000, (float) $product->price, 0.01);
        $this->assertSame('Toner', $product->assignedCategory()?->name);
        // Satuan yang sudah disesuaikan operator tidak ditimpa.
        $this->assertSame('botol', $product->unit);
    }

    public function test_removes_unused_dummy_products(): void
    {
        $this->actingAsClinicUser();

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produk Percobaan',
            'unit' => 'pcs',
            'price' => 1_000,
            'status' => 'active',
        ]);

        $this->seedCatalog();

        $this->assertSame(0, Product::query()->where('name', 'Produk Percobaan')->count());
        $this->assertSame(self::EXPECTED_TOTAL, Product::query()->count());
    }

    public function test_archives_dummy_product_that_has_sales_history(): void
    {
        $this->actingAsClinicUser();

        $dummy = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Serum Vitamin C',
            'unit' => 'botol',
            'price' => 120_000,
            'status' => 'active',
        ]);

        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $transaction = Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-LAMA-1',
            'subtotal' => 120_000,
            'paid_amount' => 120_000,
            'payment_status' => PaymentStatus::Paid,
            'issued_at' => now(),
        ]);

        $transaction->items()->create([
            'product_id' => $dummy->id,
            'name' => $dummy->name,
            'unit_price' => 120_000,
            'qty' => 1,
            'subtotal' => 120_000,
        ]);

        $this->seedCatalog();

        // Riwayat penjualannya wajib utuh.
        $this->assertDatabaseHas('transaction_items', [
            'product_id' => $dummy->id,
            'name' => 'Serum Vitamin C',
        ]);

        // Tapi produknya tidak lagi aktif di master.
        $this->assertSame('archived', $dummy->fresh()->status->value);
        $this->assertSame(self::EXPECTED_TOTAL, Product::query()->where('status', 'active')->count());
    }

    public function test_archives_dummy_product_that_is_targeted_by_a_promo(): void
    {
        $this->actingAsClinicUser();

        $dummy = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produk Promo Percobaan',
            'unit' => 'pcs',
            'price' => 20_000,
            'status' => 'active',
        ]);

        $promo = Promo::factory()->create(['tenant_id' => $this->tenant->id]);
        PromoItem::create([
            'tenant_id' => $this->tenant->id,
            'promo_id' => $promo->id,
            'promotable_type' => Product::class,
            'promotable_id' => $dummy->id,
        ]);

        $this->seedCatalog();

        $this->assertSame('archived', $dummy->fresh()->status->value);
        $this->assertDatabaseHas('promo_items', [
            'promotable_type' => Product::class,
            'promotable_id' => $dummy->id,
        ]);
    }

    public function test_master_data_endpoint_lists_only_the_twenty(): void
    {
        $this->actingAsClinicUser();
        $this->seedCatalog();

        $response = $this->getJson($this->tenantUrl('products?per_page=100'))->assertOk();

        $this->assertCount(self::EXPECTED_TOTAL, $response->json('data'));
        $this->assertSame(self::EXPECTED_TOTAL, $response->json('meta.total'));

        $first = collect($response->json('data'))->firstWhere('name', 'Niacisund Tinted');
        $this->assertSame('Sunscreen', $first['category_label']);
        $this->assertSame('95000.00', $first['price']);
    }

    public function test_products_are_sellable_at_pos_using_master_price(): void
    {
        $this->actingAsClinicUser();
        $this->seedCatalog();

        $product = Product::query()->where('name', 'CyteaPro')->firstOrFail();
        // Barang baru masuk lewat modul inventaris, bukan karangan seeder.
        $product->update(['stock_balance' => 3]);

        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'items' => [['product_id' => $product->id, 'qty' => 2]],
        ])
            ->assertCreated()
            // Harga jual diambil dari master produk, bukan kiriman klien.
            ->assertJsonPath('data.items.0.unit_price', '350000.00')
            ->assertJsonPath('data.subtotal', '700000.00');

        // Stok berkurang lewat mekanisme inventaris yang sudah ada.
        $this->assertSame(1, $product->fresh()->stock_balance);
    }

    public function test_catalog_does_not_leak_into_other_tenants(): void
    {
        $this->actingAsClinicUser();

        // Klinik lain dengan katalognya sendiri.
        $other = $this->createTenant('klinik-tetangga');
        Product::create([
            'tenant_id' => $other->id,
            'name' => 'Produk Klinik Tetangga',
            'unit' => 'pcs',
            'price' => 50_000,
            'status' => 'active',
        ]);

        $this->seedCatalog();

        $this->assertSame(self::EXPECTED_TOTAL, Product::query()->count());

        app()->instance('tenant', $other);
        $this->assertSame(1, Product::query()->count());
        $this->assertSame('Produk Klinik Tetangga', Product::query()->first()->name);
    }

    public function test_seeder_starts_with_zero_stock(): void
    {
        $this->actingAsClinicUser();
        $this->seedCatalog();

        // Tidak ada stok karangan: semua mulai dari nol sampai barang
        // benar-benar dicatat masuk.
        $this->assertSame(0, Product::query()->where('stock_balance', '!=', 0)->count());
        $this->assertSame(0, Product::query()->where('min_threshold', '!=', 0)->count());
    }
}
