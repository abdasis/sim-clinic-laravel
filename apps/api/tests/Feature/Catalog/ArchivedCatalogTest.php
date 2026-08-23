<?php

namespace Tests\Feature\Catalog;

use App\Actions\Chatbot\SearchServicesAction;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Yang diarsipkan hilang dari tempat menjual, tetap ada di tempat mencatat.
 *
 * Layanan dan produk yang tidak ditawarkan lagi tidak boleh dihapus: nota
 * dan rekam medis lama menyebut namanya, dan menghapusnya berarti riwayat
 * pasien menunjuk ke sesuatu yang tidak ada. Arsip adalah jalan tengahnya —
 * tapi hanya berguna bila kasir benar-benar tidak lagi bisa memilihnya.
 */
class ArchivedCatalogTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();
    }

    private function makeService(string $name, string $status = 'active'): Service
    {
        return Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'price' => 300000,
            'duration_minutes' => 60,
            'status' => $status,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function names(string $path, array $query = []): array
    {
        return collect(
            $this->getJson($this->tenantUrl($path).'?'.http_build_query($query))
                ->assertOk()->json('data'),
        )->pluck('name')->all();
    }

    /** Mengarsipkan layanan: statusnya berubah, barisnya tidak hilang. */
    public function test_archiving_a_service_keeps_the_row(): void
    {
        $service = $this->makeService('Facial Lama');

        $this->deleteJson($this->tenantUrl("services/{$service->id}"))->assertOk();

        $this->assertSame('archived', $service->fresh()->status->value);
        $this->assertNotNull(Service::withoutGlobalScopes()->find($service->id));
    }

    public function test_archiving_a_product_keeps_the_row(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Serum Lama',
            'status' => 'active',
        ]);

        $this->deleteJson($this->tenantUrl("products/{$product->id}"))->assertOk();

        $this->assertSame('archived', $product->fresh()->status->value);
    }

    /**
     * Inti kekhawatirannya: yang diarsipkan tidak boleh lagi bisa dijual.
     * Katalog kasir memakai endpoint daftar yang sama tanpa penyaring status,
     * jadi bawaan endpoint itulah yang menjaganya.
     */
    public function test_an_archived_service_is_gone_from_the_cashier_catalog(): void
    {
        $this->makeService('Facial Aktif');
        $this->makeService('Facial Arsip', 'archived');

        $this->assertSame(['Facial Aktif'], $this->names('services', ['per_page' => 100]));
    }

    public function test_an_archived_product_is_gone_from_the_cashier_catalog(): void
    {
        foreach (['Serum Aktif' => 'active', 'Serum Arsip' => 'archived'] as $name => $status) {
            Product::factory()->create([
                'tenant_id' => $this->tenant->id,
                'name' => $name,
                'status' => $status,
                'type' => 'retail',
            ]);
        }

        $this->assertSame(
            ['Serum Aktif'],
            $this->names('products', ['per_page' => 100, 'filter' => ['type' => 'retail']]),
        );
    }

    /** Arsipnya tetap bisa dibuka bila memang diminta, untuk ditinjau. */
    public function test_the_archive_is_still_reachable_on_request(): void
    {
        $this->makeService('Facial Aktif');
        $this->makeService('Facial Arsip', 'archived');

        $this->assertSame(
            ['Facial Arsip'],
            $this->names('services', ['filter' => ['status' => 'archived']]),
        );
    }

    /** Chatbot pun tidak boleh menawarkan yang sudah diarsipkan. */
    public function test_the_chatbot_does_not_offer_archived_services(): void
    {
        $this->makeService('Facial Arsip', 'archived');

        $found = app(SearchServicesAction::class)->handle('facial');

        $this->assertSame([], $found);
    }

    /**
     * Arsip bukan jalan buntu: layanan musiman yang ditawarkan lagi bisa
     * dikembalikan lewat formulir ubah, tanpa perlu dibuat ulang dari nol —
     * membuat ulang berarti riwayat lama menunjuk ke baris yang berbeda.
     */
    public function test_an_archived_service_can_be_brought_back(): void
    {
        $service = $this->makeService('Facial Musiman', 'archived');

        $this->putJson($this->tenantUrl("services/{$service->id}"), [
            'name' => 'Facial Musiman',
            'price' => 300000,
            'duration_minutes' => 60,
            'status' => 'active',
        ])->assertOk();

        $this->assertSame('active', $service->fresh()->status->value);
        $this->assertSame(
            ['Facial Musiman'],
            $this->names('services', ['per_page' => 100]),
        );
    }

    /** Yang dikembalikan memakai baris yang sama, bukan baris baru. */
    public function test_bringing_it_back_reuses_the_same_row(): void
    {
        $service = $this->makeService('Facial Musiman', 'archived');

        $this->putJson($this->tenantUrl("services/{$service->id}"), [
            'name' => 'Facial Musiman',
            'price' => 300000,
            'duration_minutes' => 60,
            'status' => 'active',
        ])->assertOk();

        $this->assertSame(1, Service::query()->count());
        $this->assertSame($service->id, Service::query()->first()->id);
    }
}
