<?php

namespace Tests\Feature\Product;

use App\Enums\ClinicRole;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Produk yang stoknya pernah dicatat masuk.
 *
 * Foreign key stock_movements.product_id memakai RESTRICT di PostgreSQL,
 * sementara migrasinya dilewati di SQLite. Akibatnya penghapusan yang mulus
 * di pengujian meledak jadi galat 500 di server — kelas kekeliruan yang hanya
 * kelihatan bila suite PostgreSQL ikut dijalankan.
 */
class ProductWithStockMovementDeleteTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function stocked(string $name): Product
    {
        $product = Product::factory()->archived()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'stock_balance' => 0,
        ]);

        $this->postJson($this->tenantUrl("products/{$product->id}/stock-movements"), [
            'type' => 'in',
            'quantity' => 10,
            'note' => 'Pembelian percobaan',
        ])->assertCreated();

        return $product;
    }

    public function test_deletes_product_together_with_its_stock_ledger(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $product = $this->stocked('Produk Percobaan Berstok');

        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id]);

        $this->deleteJson($this->tenantUrl("products/{$product->id}/force"))->assertOk();

        // Kartu stok tidak punya arti tanpa produknya, jadi ikut hilang —
        // bukan tertinggal menggantung dan bukan menggagalkan penghapusan.
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $product->id]);
    }
}
