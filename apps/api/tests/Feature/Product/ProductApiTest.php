<?php

namespace Tests\Feature\Product;

use App\Enums\ClinicRole;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_create_ignores_stock_balance_from_request(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('products'), [
            'name' => 'Serum Vitamin C',
            'unit' => 'botol',
            'min_threshold' => 5,
            'price' => 150000,
            'stock_balance' => 999,
        ])
            ->assertCreated()
            ->assertJsonPath('data.stock_balance', 0)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_low_stock', true);
    }

    public function test_update_cannot_change_stock_balance(): void
    {
        $this->actingAsClinicUser();
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_balance' => 12,
        ]);

        $this->putJson($this->tenantUrl('products/'.$product->id), [
            'name' => 'Nama Baru',
            'unit' => $product->unit,
            'min_threshold' => $product->min_threshold,
            'price' => $product->price,
            'stock_balance' => 999,
        ])->assertOk();

        $this->assertSame(12, $product->fresh()->stock_balance);
    }

    public function test_validation_rejects_negative_price(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('products'), [
            'name' => 'Produk',
            'unit' => 'pcs',
            'min_threshold' => 0,
            'price' => -1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    public function test_list_hides_archived_unless_requested(): void
    {
        $this->actingAsClinicUser();
        Product::factory()->create(['tenant_id' => $this->tenant->id]);
        Product::factory()->archived()->create(['tenant_id' => $this->tenant->id]);

        $this->getJson($this->tenantUrl('products'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson($this->tenantUrl('products').'?filter[status]=archived')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson($this->tenantUrl('products').'?filter[status]=all')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_archive_sets_status_instead_of_deleting(): void
    {
        $this->actingAsClinicUser();
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->deleteJson($this->tenantUrl('products/'.$product->id))->assertOk();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => 'archived',
        ]);
    }

    public function test_role_without_product_access_is_forbidden(): void
    {
        // Matriks peran tidak memberi dokter akses modul produk sama sekali.
        $this->actingAsClinicUser(ClinicRole::Doctor);

        $this->getJson($this->tenantUrl('products'))->assertForbidden();

        $this->postJson($this->tenantUrl('products'), [
            'name' => 'Produk',
            'unit' => 'pcs',
            'min_threshold' => 0,
            'price' => 1000,
        ])->assertForbidden();
    }
}
