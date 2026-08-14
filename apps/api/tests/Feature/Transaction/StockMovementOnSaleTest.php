<?php

namespace Tests\Feature\Transaction;

use App\Actions\Transaction\CancelTransactionAction;
use App\Enums\ClinicRole;
use App\Enums\StockMovementType;
use App\Models\Patient;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Saldo stok hanya boleh bergerak lewat mutasi yang tercatat. Menjual
 * mengurangi, membatalkan mengembalikan, dan setiap langkah meninggalkan
 * jejak saldo sesudahnya supaya selisih bisa ditelusuri.
 */
class StockMovementOnSaleTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_selling_a_product_reduces_stock(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $product = $this->makeProduct(10);

        $this->recordSale($product, qty: 3);

        $this->assertSame(7, $product->fresh()->stock_balance);
    }

    public function test_sale_records_movement_with_balance_after(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $product = $this->makeProduct(10);

        $transaction = $this->recordSale($product, qty: 3);

        $movement = StockMovement::where('product_id', $product->id)->latest('id')->first();

        $this->assertNotNull($movement);
        $this->assertSame(StockMovementType::SoldPos, $movement->type);
        $this->assertSame(3, $movement->quantity);
        $this->assertSame(7, $movement->balance_after);
        $this->assertSame($transaction->id, $movement->related_id);
    }

    public function test_cancelling_a_transaction_returns_the_stock(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $product = $this->makeProduct(10);

        $transaction = $this->recordSale($product, qty: 3);
        $this->assertSame(7, $product->fresh()->stock_balance);

        app(CancelTransactionAction::class)->handle($transaction);

        $this->assertSame(10, $product->fresh()->stock_balance, 'Stok harus kembali utuh setelah pembatalan.');
    }

    public function test_double_cancel_does_not_return_stock_twice(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $product = $this->makeProduct(10);

        $transaction = $this->recordSale($product, qty: 3);

        app(CancelTransactionAction::class)->handle($transaction);

        try {
            app(CancelTransactionAction::class)->handle($transaction->fresh());
        } catch (\Throwable) {
            // Penolakan memang yang diharapkan; yang diuji adalah saldonya.
        }

        $this->assertSame(10, $product->fresh()->stock_balance, 'Stok tidak boleh dikembalikan berlipat.');
    }

    public function test_sale_beyond_available_stock_is_rejected(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $product = $this->makeProduct(2);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'items' => [['product_id' => $product->id, 'qty' => 5]],
        ])->assertStatus(422);

        $this->assertSame(2, $product->fresh()->stock_balance, 'Stok tidak boleh berubah saat penjualan ditolak.');
        $this->assertSame(0, Transaction::withTrashed()->count(), 'Transaksi gagal tidak boleh tersimpan sebagian.');
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
