<?php

namespace Tests\Feature\Category;

use App\Enums\CategorizableType;
use App\Enums\ClinicRole;
use App\Enums\ServiceStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Kategori dipakai bersama layanan, produk, dan pengeluaran lewat satu tabel.
 * Yang dijaga di sini: jenisnya tidak tercampur, namanya tidak ganda, dan
 * kategori yang masih dipakai tidak bisa lenyap begitu saja.
 */
class CategoryApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function make(string $name, CategorizableType $type = CategorizableType::Service): Category
    {
        return Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'type' => $type,
            'status' => ServiceStatus::Active,
        ]);
    }

    public function test_admin_creates_a_category(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('categories'), [
            'name' => 'Skinbooster',
            'type' => 'service',
            'description' => 'Perawatan peremajaan kulit',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Skinbooster')
            ->assertJsonPath('data.type', 'service')
            ->assertJsonPath('data.type_label', 'Layanan')
            // Sama seperti layanan: status bawaan harus ikut terkirim.
            ->assertJsonPath('data.status', 'active');

        $this->assertSame(1, Category::query()->count());
    }

    /**
     * Daftar selalu disaring per jenis: formulir layanan tidak boleh
     * menawarkan kategori pengeluaran.
     */
    public function test_listing_is_filtered_by_type(): void
    {
        $this->actingAsClinicUser();

        $this->make('Skinbooster', CategorizableType::Service);
        $this->make('Serum', CategorizableType::Product);
        $this->make('Gaji', CategorizableType::Expense);

        $this->getJson($this->tenantUrl('categories?filter[type]=service'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Skinbooster');

        $this->getJson($this->tenantUrl('categories?filter[type]=expense'))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Gaji');
    }

    public function test_duplicate_name_within_a_type_is_rejected(): void
    {
        $this->actingAsClinicUser();
        $this->make('Serum', CategorizableType::Product);

        $this->postJson($this->tenantUrl('categories'), ['name' => 'Serum', 'type' => 'product'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /** Nama yang sama di jenis berbeda tetap boleh — keduanya tidak pernah bertemu. */
    public function test_same_name_in_a_different_type_is_allowed(): void
    {
        $this->actingAsClinicUser();
        $this->make('Lain-lain', CategorizableType::Product);

        $this->postJson($this->tenantUrl('categories'), ['name' => 'Lain-lain', 'type' => 'expense'])
            ->assertCreated();
    }

    public function test_renaming_to_its_own_name_is_not_a_conflict(): void
    {
        $this->actingAsClinicUser();
        $category = $this->make('Serum', CategorizableType::Product);

        $this->putJson($this->tenantUrl('categories/'.$category->id), [
            'name' => 'Serum',
            'type' => 'product',
            'description' => 'Diperbarui',
        ])->assertOk();
    }

    public function test_destroy_archives_instead_of_deleting(): void
    {
        $this->actingAsClinicUser();
        $category = $this->make('Skinbooster');

        $this->deleteJson($this->tenantUrl('categories/'.$category->id))->assertOk();

        $this->assertSame(ServiceStatus::Archived, $category->refresh()->status);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    /**
     * Pivot-nya cascade. Kalau hapus permanen dibiarkan, kategori lenyap dari
     * seluruh item yang memakainya tanpa ada yang tahu.
     */
    public function test_permanent_delete_is_refused_while_still_in_use(): void
    {
        $this->actingAsClinicUser();
        $category = $this->make('Skinbooster');

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $service->syncCategory($category->id);

        $this->deleteJson($this->tenantUrl('categories/'.$category->id.'/force'))
            ->assertStatus(422);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_unused_category_can_be_deleted_permanently(): void
    {
        $this->actingAsClinicUser();
        $category = $this->make('Skinbooster');

        $this->deleteJson($this->tenantUrl('categories/'.$category->id.'/force'))->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_usage_count_is_reported(): void
    {
        $this->actingAsClinicUser();
        $category = $this->make('Serum', CategorizableType::Product);

        foreach (range(1, 3) as $i) {
            Product::factory()->create(['tenant_id' => $this->tenant->id])
                ->syncCategory($category->id);
        }

        $this->getJson($this->tenantUrl('categories?filter[type]=product'))
            ->assertOk()
            ->assertJsonPath('data.0.usage_count', 3);
    }

    public function test_cashier_cannot_manage_categories(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->getJson($this->tenantUrl('categories'))->assertForbidden();

        $this->postJson($this->tenantUrl('categories'), ['name' => 'Apa saja', 'type' => 'service'])
            ->assertForbidden();
    }

    public function test_doctor_may_read_but_not_change(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);

        $this->getJson($this->tenantUrl('categories?filter[type]=service'))->assertOk();

        $this->postJson($this->tenantUrl('categories'), ['name' => 'Baru', 'type' => 'service'])
            ->assertForbidden();
    }

    /** Kategori klinik lain tidak boleh terlihat maupun tersentuh. */
    public function test_categories_are_isolated_per_clinic(): void
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

        $this->getJson($this->tenantUrl('categories?filter[type]=service'))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson($this->tenantUrl('categories/'.$milikLain->id))->assertNotFound();
    }
}
