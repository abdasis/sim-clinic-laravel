<?php

namespace Tests\Feature\Transaction;

use App\Enums\ClinicRole;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\TransactionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Item transaksi mewakili tepat satu produk atau satu layanan, dan master
 * yang sudah terjual tidak boleh dihapus permanen.
 *
 * CHECK dan FK RESTRICT dilewati di SQLite, jadi bagian itu hanya bermakna
 * di PostgreSQL dan otomatis dilewati pada driver lain.
 */
class TransactionItemIntegrityTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (\DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('CHECK dan FK RESTRICT hanya ditegakkan di PostgreSQL.');
        }

        $this->actingAsClinicUser(ClinicRole::Admin);
    }

    public function test_item_without_any_reference_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $this->makeItem(productId: null, serviceId: null);
    }

    public function test_item_with_both_references_is_rejected(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = $this->makeService();

        $this->expectException(QueryException::class);

        $this->makeItem(productId: $product->id, serviceId: $service->id);
    }

    public function test_item_with_exactly_one_reference_is_accepted(): void
    {
        $service = $this->makeService();

        $item = $this->makeItem(productId: null, serviceId: $service->id);

        $this->assertDatabaseHas('transaction_items', ['id' => $item->id]);
    }

    public function test_sold_product_cannot_be_force_deleted(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_balance' => 10,
        ]);

        $this->recordSale($product);

        $this->expectException(QueryException::class);

        $product->delete();
    }

    public function test_sold_service_cannot_be_deleted(): void
    {
        $service = $this->makeService();
        $this->makeItem(productId: null, serviceId: $service->id);

        $this->expectException(QueryException::class);

        $service->delete();
    }

    private function makeService(): Service
    {
        return Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial',
            'price' => 100000,
            'status' => 'active',
        ]);
    }

    private function makeItem(?int $productId, ?int $serviceId): TransactionItem
    {
        $transaction = Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'cashier_id' => auth()->id(),
        ]);

        return TransactionItem::create([
            'tenant_id' => $this->tenant->id,
            'transaction_id' => $transaction->id,
            'product_id' => $productId,
            'service_id' => $serviceId,
            'name' => 'Item uji',
            'unit_price' => 100000,
            'qty' => 1,
            'subtotal' => 100000,
        ]);
    }

    private function recordSale(Product $product): Transaction
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        return app(TransactionService::class)->create([
            'patient_id' => $patient->id,
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ]);
    }
}
