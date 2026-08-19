<?php

namespace Tests\Feature\Service;

use App\Enums\ClinicRole;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Promo;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Menghapus permanen entri katalog dibedakan dari mengarsipkan. Yang belum
 * meninggalkan jejak boleh benar-benar dibuang — berguna saat merapikan data
 * percobaan. Yang sudah pernah terjual atau dijadwalkan tidak boleh, karena
 * riwayatnya akan kehilangan acuan; untuk itu arsip yang tepat.
 */
class CatalogHardDeleteTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_unused_service_can_be_deleted_for_good(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $service = $this->makeService();

        $this->deleteJson($this->tenantUrl('services/'.$service->id.'/force'))
            ->assertOk();

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    public function test_sold_service_is_refused_with_a_reason(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $service = $this->makeService();
        $this->sellService($service);

        $this->deleteJson($this->tenantUrl('services/'.$service->id.'/force'))
            ->assertStatus(422)
            // Nomor notanya ikut disebut: tanpa itu admin masih harus menebak
            // nota mana yang menahan layanan ini.
            ->assertJsonPath('message', __('catalog.referenced_by_transaction', [
                'ref' => Transaction::query()->value('invoice_number'),
            ]));

        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }

    public function test_unused_product_can_be_deleted_for_good(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $product = $this->makeProduct();

        $this->deleteJson($this->tenantUrl('products/'.$product->id.'/force'))
            ->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_archiving_still_keeps_the_row(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $service = $this->makeService();

        $this->deleteJson($this->tenantUrl('services/'.$service->id))->assertOk();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'status' => 'archived',
        ]);
    }

    public function test_catalog_of_another_clinic_cannot_be_deleted(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $other = $this->createTenant('klinik-lain');

        $foreign = Service::create([
            'tenant_id' => $other->id,
            'name' => 'Facial Klinik Lain',
            'price' => 100_000,
            'duration_minutes' => 30,
            'status' => 'active',
        ]);

        app()->instance('tenant', $this->tenant);

        $this->deleteJson($this->tenantUrl('services/'.$foreign->id.'/force'))
            ->assertNotFound();

        $this->assertDatabaseHas('services', ['id' => $foreign->id]);
    }

    /**
     * Promo menunjuk sasarannya secara polimorfik. Pemeriksaan yang memakai
     * kolom service_id di tabel ini sempat lolos di SQLite dan baru meledak
     * di PostgreSQL — kolomnya memang tidak pernah ada.
     */
    public function test_service_inside_a_promo_is_refused(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $service = $this->makeService();

        $promo = Promo::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Promo Uji',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
            'is_active' => true,
        ]);

        $promo->items()->create([
            'tenant_id' => $this->tenant->id,
            'promotable_type' => 'service',
            'promotable_id' => $service->id,
        ]);

        $this->deleteJson($this->tenantUrl('services/'.$service->id.'/force'))
            ->assertStatus(422)
            ->assertJsonPath('message', __('catalog.referenced_by_promo', ['ref' => 'Promo Uji']));
    }

    private function makeService(): Service
    {
        return Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Uji',
            'price' => 100_000,
            'duration_minutes' => 30,
            'status' => 'active',
        ]);
    }

    private function makeProduct(): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sunscreen Uji',
            'unit' => 'pcs',
            'price' => 100_000,
            'stock_balance' => 0,
            'status' => 'active',
        ]);
    }

    private function sellService(Service $service): void
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'items' => [['service_id' => $service->id, 'qty' => 1]],
        ])->assertCreated();
    }
}
