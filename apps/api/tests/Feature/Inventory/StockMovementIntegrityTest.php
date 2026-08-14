<?php

namespace Tests\Feature\Inventory;

use App\Actions\Transaction\CancelTransactionAction;
use App\Enums\ClinicRole;
use App\Enums\StockMovementType;
use App\Models\Activity;
use App\Models\Patient;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Services\StockService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Saldo stok tidak boleh minus, tiap mutasi meninggalkan jejak yang bisa
 * dibaca, dan mutasi yang lahir dari transaksi harus bisa ditelusuri balik.
 */
class StockMovementIntegrityTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_manual_stock_out_beyond_balance_is_rejected(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $product = $this->makeProduct(3);

        $this->postJson(
            $this->tenantUrl('products/'.$product->id.'/stock-movements'),
            ['type' => 'out_manual', 'quantity' => 5],
        )->assertStatus(422);

        $this->assertSame(3, $product->fresh()->stock_balance);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_stock_out_down_to_zero_is_allowed(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $product = $this->makeProduct(3);

        $this->postJson(
            $this->tenantUrl('products/'.$product->id.'/stock-movements'),
            ['type' => 'out_manual', 'quantity' => 3],
        )->assertCreated();

        $this->assertSame(0, $product->fresh()->stock_balance);
    }

    public function test_every_movement_is_recorded_narratively(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $product = $this->makeProduct(0);

        app(StockService::class)->adjust($product, StockMovementType::In, 10, 'Stok awal');

        $activity = Activity::query()->where('event', 'inventory.stock.adjusted')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('Menyesuaikan stok', $activity->description);
        $this->assertStringContainsString($product->name, $activity->description);
        $this->assertSame(10, $activity->properties['attributes']['balance_after']);
    }

    public function test_related_type_is_stored_as_a_morph_alias(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $product = $this->makeProduct(10);

        $transaction = $this->recordSale($product, qty: 2);

        $movement = StockMovement::query()->latest('id')->first();

        $this->assertSame('transaction', $movement->related_type);
        $this->assertSame($transaction->id, $movement->related_id);
        $this->assertTrue($movement->related->is($transaction));
    }

    public function test_movements_can_be_traced_back_from_a_transaction(): void
    {
        // Riwayat stok modul inventaris — hanya admin yang boleh membacanya.
        $this->actingAsClinicUser(ClinicRole::Admin);
        $product = $this->makeProduct(10);

        $transaction = $this->recordSale($product, qty: 2);
        app(CancelTransactionAction::class)->handle($transaction);

        // Penjualan dan pembatalannya sama-sama menempel pada transaksi itu.
        $response = $this->getJson(
            $this->tenantUrl('transactions/'.$transaction->id.'/stock-movements'),
        );

        $response->assertOk();
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('data.0.related_type', 'transaction');
    }

    public function test_movements_of_another_tenant_are_not_listed(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $product = $this->makeProduct(10);
        $this->recordSale($product, qty: 1);

        $other = $this->createTenant('klinik-lain');
        $foreignProduct = Product::factory()->create([
            'tenant_id' => $other->id,
            'stock_balance' => 5,
        ]);
        app(StockService::class)->adjust($foreignProduct, StockMovementType::In, 5);

        app()->instance('tenant', $this->tenant);

        $this->getJson($this->tenantUrl('products/'.$product->id.'/stock-movements'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    private function makeProduct(int $balance): Product
    {
        return Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_balance' => $balance,
            'price' => 50000,
        ]);
    }

    private function recordSale(Product $product, int $qty): Transaction
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        return app(TransactionService::class)->create([
            'patient_id' => $patient->id,
            'items' => [['product_id' => $product->id, 'qty' => $qty]],
        ]);
    }
}
