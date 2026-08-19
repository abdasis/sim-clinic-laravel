<?php

namespace Tests\Feature\Product;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Baris yang diarsipkan harus bisa dijangkau lagi. Daftar master selalu
 * menyaring status aktif, jadi tanpa penyaring status arsipnya tidak punya
 * jalan untuk dilihat — apalagi dibereskan.
 */
class ArchivedCatalogVisibilityTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_archived_products_are_hidden_by_default_but_reachable_by_filter(): void
    {
        $this->actingAsClinicUser();

        Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Produk Aktif']);
        Product::factory()->archived()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produk Arsip',
        ]);

        $default = collect($this->getJson($this->tenantUrl('products'))->json('data'))->pluck('name');
        $this->assertNotContains('Produk Arsip', $default);

        $archived = collect(
            $this->getJson($this->tenantUrl('products?filter[status]=archived'))->json('data')
        )->pluck('name');

        $this->assertContains('Produk Arsip', $archived);
        $this->assertNotContains('Produk Aktif', $archived);
    }

    public function test_product_status_filter_accepts_several_values_at_once(): void
    {
        $this->actingAsClinicUser();

        Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Produk Aktif']);
        Product::factory()->archived()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produk Arsip',
        ]);

        // Kendali penyaringnya pilih-banyak, jadi nilainya bisa daftar berkoma.
        $names = collect(
            $this->getJson($this->tenantUrl('products?filter[status]=active,archived'))->json('data')
        )->pluck('name');

        $this->assertContains('Produk Aktif', $names);
        $this->assertContains('Produk Arsip', $names);
    }

    public function test_archived_services_are_reachable_by_filter(): void
    {
        $this->actingAsClinicUser();

        Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Layanan Aktif']);
        Service::factory()->archived()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Layanan Arsip',
        ]);

        $default = collect($this->getJson($this->tenantUrl('services'))->json('data'))->pluck('name');
        $this->assertNotContains('Layanan Arsip', $default);

        $names = collect(
            $this->getJson($this->tenantUrl('services?filter[status]=active,archived'))->json('data')
        )->pluck('name');

        $this->assertContains('Layanan Aktif', $names);
        $this->assertContains('Layanan Arsip', $names);
    }
}
