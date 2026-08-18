<?php

namespace Tests\Feature\Category;

use App\Enums\CategorizableType;
use App\Enums\ServiceStatus;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Kategori dipasang lewat pivot polimorfik, bukan kolom. Yang dijaga: satu
 * item hanya punya satu kategori, jenisnya cocok, dan kontrak lama di response
 * tetap terisi supaya frontend tidak putus.
 */
class CategoryAssignmentTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function category(string $name, CategorizableType $type): Category
    {
        return Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'type' => $type,
            'status' => ServiceStatus::Active,
        ]);
    }

    public function test_service_is_created_with_its_category(): void
    {
        $this->actingAsClinicUser();
        $category = $this->category('Skinbooster', CategorizableType::Service);

        $this->postJson($this->tenantUrl('services'), [
            'name' => 'Glow Booster',
            'category_id' => $category->id,
            'price' => 500_000,
            'duration_minutes' => 60,
        ])
            ->assertCreated()
            ->assertJsonPath('data.category_id', $category->id)
            // Kunci lama dipertahankan supaya tabel dan detail yang sudah ada
            // tidak perlu ikut berubah.
            ->assertJsonPath('data.category', 'Skinbooster')
            ->assertJsonPath('data.category_label', 'Skinbooster');
    }

    /** Satu item satu kategori: mengubahnya mengganti, bukan menambah. */
    public function test_changing_the_category_replaces_it(): void
    {
        $this->actingAsClinicUser();
        $lama = $this->category('Skinbooster', CategorizableType::Service);
        $baru = $this->category('Facial & Peeling', CategorizableType::Service);

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $service->syncCategory($lama->id);

        $this->putJson($this->tenantUrl('services/'.$service->id), [
            'name' => $service->name,
            'category_id' => $baru->id,
            'price' => 100_000,
            'duration_minutes' => 30,
        ])->assertOk();

        $this->assertSame('Facial & Peeling', $service->refresh()->assignedCategory()?->name);
        $this->assertDatabaseCount('categorizables', 1);
    }

    public function test_category_can_be_removed_again(): void
    {
        $this->actingAsClinicUser();
        $category = $this->category('Skinbooster', CategorizableType::Service);

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $service->syncCategory($category->id);

        $this->putJson($this->tenantUrl('services/'.$service->id), [
            'name' => $service->name,
            'category_id' => null,
            'price' => 100_000,
            'duration_minutes' => 30,
        ])->assertOk();

        $this->assertNull($service->refresh()->assignedCategory());
    }

    /**
     * Kolom `type` ada justru untuk ini: tanpa pemeriksaan, kategori produk
     * bisa menempel ke layanan dan daftar pilihannya jadi campur aduk.
     */
    public function test_category_of_the_wrong_type_is_rejected(): void
    {
        $this->actingAsClinicUser();
        $kategoriProduk = $this->category('Serum', CategorizableType::Product);

        $this->postJson($this->tenantUrl('services'), [
            'name' => 'Glow Booster',
            'category_id' => $kategoriProduk->id,
            'price' => 500_000,
            'duration_minutes' => 60,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_category_from_another_clinic_is_rejected(): void
    {
        $lain = $this->createTenant('klinik-tetangga');
        $milikLain = Category::create([
            'tenant_id' => $lain->id,
            'name' => 'Milik Tetangga',
            'type' => CategorizableType::Service,
            'status' => ServiceStatus::Active,
        ]);

        $this->tenant = $this->createTenant('klinik-kita');
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('services'), [
            'name' => 'Glow Booster',
            'category_id' => $milikLain->id,
            'price' => 500_000,
            'duration_minutes' => 60,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_product_carries_its_category_too(): void
    {
        $this->actingAsClinicUser();
        $category = $this->category('Serum', CategorizableType::Product);

        $this->postJson($this->tenantUrl('products'), [
            'name' => 'Serum Vitamin C',
            'category_id' => $category->id,
            'unit' => 'botol',
            'min_threshold' => 3,
            'price' => 150_000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.category_label', 'Serum');
    }

    public function test_expense_requires_a_category(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('expenses'), [
            'spent_at' => '2026-05-31',
            'description' => 'Bayar listrik',
            'amount' => 500_000,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    /**
     * Rekap pengeluaran dikelompokkan lewat pivot sekarang. Baris tanpa
     * kategori tetap dihitung — kalau disembunyikan, jumlah per kategori tidak
     * akan pernah sama dengan totalnya.
     */
    public function test_expense_summary_groups_through_the_pivot(): void
    {
        $this->actingAsClinicUser();
        $gaji = $this->category('Gaji', CategorizableType::Expense);

        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'spent_at' => '2026-05-10', 'amount' => 1_000_000])
            ->syncCategory($gaji->id);
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'spent_at' => '2026-05-11', 'amount' => 250_000]);

        $response = $this->getJson($this->tenantUrl('expenses/summary?from=2026-05-01&to=2026-05-31'))
            ->assertOk();

        $this->assertEqualsWithDelta(1_250_000, $response->json('data.total'), 0.01);

        $byCategory = collect($response->json('data.by_category'));

        $this->assertEqualsWithDelta(1_000_000, $byCategory->firstWhere('category_label', 'Gaji')['total'], 0.01);
        $this->assertEqualsWithDelta(250_000, $byCategory->firstWhere('category_label', 'Tanpa Kategori')['total'], 0.01);
        $this->assertEqualsWithDelta(1_250_000, $byCategory->sum('total'), 0.01);
    }

    public function test_listing_services_does_not_query_categories_per_row(): void
    {
        $this->actingAsClinicUser();
        $category = $this->category('Skinbooster', CategorizableType::Service);

        foreach (range(1, 5) as $i) {
            Service::factory()->create(['tenant_id' => $this->tenant->id])->syncCategory($category->id);
        }

        $queries = 0;
        \DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->getJson($this->tenantUrl('services'))->assertOk();

        // Lima baris tidak boleh berarti lima kueri kategori. Angkanya longgar
        // supaya tes tidak rapuh, tapi cukup ketat untuk menangkap N+1.
        $this->assertLessThan(15, $queries, 'Daftar layanan tampaknya menembak satu kueri kategori per baris.');
    }

    public function test_product_listing_reports_its_category(): void
    {
        $this->actingAsClinicUser();
        $category = $this->category('Serum', CategorizableType::Product);

        Product::factory()->create(['tenant_id' => $this->tenant->id])->syncCategory($category->id);

        $this->getJson($this->tenantUrl('products'))
            ->assertOk()
            ->assertJsonPath('data.0.category_label', 'Serum')
            ->assertJsonPath('data.0.category_id', $category->id);
    }
}
