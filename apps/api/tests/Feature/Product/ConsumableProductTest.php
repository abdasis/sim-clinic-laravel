<?php

namespace Tests\Feature\Product;

use App\Enums\ProductType;
use App\Models\Patient;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Bahan pakai treatment — masker, spon, sarung tangan — tinggal di master
 * produk yang sama supaya stok dan mutasinya memakai mekanisme yang sudah
 * ada, tapi tidak boleh bocor ke kasir.
 */
class ConsumableProductTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_existing_products_default_to_retail(): void
    {
        $this->actingAsClinicUser();

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Serum Tanpa Peruntukan',
            'unit' => 'pcs',
            'price' => 100_000,
            'status' => 'active',
        ]);

        $this->assertSame(ProductType::Retail, $product->fresh()->type);
    }

    public function test_creates_consumable_without_asking_for_a_selling_price(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('products'), [
            'name' => 'Masker Facial',
            'type' => ProductType::Consumable->value,
            'unit' => 'pcs',
            'min_threshold' => 10,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', ProductType::Consumable->value)
            ->assertJsonPath('data.type_label', 'Bahan Pakai')
            ->assertJsonPath('data.is_sellable', false)
            // Tidak diminta, jadi tidak dikarang: nol, bukan angka tebakan.
            ->assertJsonPath('data.price', '0.00');
    }

    public function test_retail_product_still_requires_a_price(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('products'), [
            'name' => 'Serum Tanpa Harga',
            'type' => ProductType::Retail->value,
            'unit' => 'pcs',
            'min_threshold' => 5,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    public function test_cashier_catalog_excludes_consumables(): void
    {
        $this->actingAsClinicUser();

        Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Serum Jual']);
        Product::factory()->consumable()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Spon Facial',
        ]);

        $names = collect(
            $this->getJson($this->tenantUrl('products?filter[type]=retail&per_page=100'))
                ->assertOk()
                ->json('data')
        )->pluck('name');

        $this->assertContains('Serum Jual', $names);
        $this->assertNotContains('Spon Facial', $names);
    }

    public function test_inventory_still_sees_consumables(): void
    {
        $this->actingAsClinicUser();

        Product::factory()->consumable()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sarung Tangan',
        ]);

        // Tanpa penyaring peruntukan, master produk memuat keduanya — di
        // sinilah stok bahan pakai dikelola.
        $names = collect(
            $this->getJson($this->tenantUrl('products?per_page=100'))->assertOk()->json('data')
        )->pluck('name');

        $this->assertContains('Sarung Tangan', $names);
    }

    public function test_consumable_cannot_be_sold_even_when_requested_directly(): void
    {
        $this->actingAsClinicUser();

        $consumable = Product::factory()->consumable()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Masker Facial',
            'stock_balance' => 50,
        ]);

        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        // Menyembunyikannya dari katalog tidak cukup; permintaan yang menembak
        // langsung ke API pun harus ditolak.
        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'items' => [['product_id' => $consumable->id, 'qty' => 1]],
        ])->assertStatus(422);

        // Stoknya tidak berkurang oleh percobaan yang gagal.
        $this->assertSame(50, $consumable->fresh()->stock_balance);
    }

    public function test_consumable_stock_moves_through_the_inventory_module(): void
    {
        $this->actingAsClinicUser();

        $consumable = Product::factory()->consumable()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Spon Facial',
            'stock_balance' => 0,
        ]);

        $this->postJson($this->tenantUrl("products/{$consumable->id}/stock-movements"), [
            'type' => 'in',
            'quantity' => 100,
            'note' => 'Pembelian awal',
        ])->assertCreated();

        $this->assertSame(100, $consumable->fresh()->stock_balance);

        $this->postJson($this->tenantUrl("products/{$consumable->id}/stock-movements"), [
            'type' => 'out_manual',
            'quantity' => 8,
            'note' => 'Dipakai treatment hari ini',
        ])->assertCreated();

        $this->assertSame(92, $consumable->fresh()->stock_balance);
    }
}
