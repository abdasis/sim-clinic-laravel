<?php

namespace Tests\Feature\Transaction;

use App\Enums\ClinicRole;
use App\Enums\StockMovementType;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Membersihkan data percobaan harus mengembalikan klinik ke keadaan semula,
 * bukan sekadar mengosongkan daftar transaksi. Penjualan produk sudah
 * memotong saldo stok; kalau transaksinya hilang tanpa saldonya dipulihkan,
 * stok menyusut tanpa penjualan yang menjelaskannya.
 */
class PurgeTransactionsCommandTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_purge_removes_transactions_and_restores_stock(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $product = $this->makeProduct(10);

        $this->sell($product, 3);
        $this->sell($product, 2);

        $this->assertSame(5, $product->fresh()->stock_balance);
        $this->assertSame(2, Transaction::count());

        $this->artisan('clinic:purge-transactions', [
            '--tenant' => $this->tenant->slug,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(0, Transaction::withTrashed()->count());
        $this->assertSame(10, $product->fresh()->stock_balance);
        $this->assertDatabaseCount('transaction_items', 0);
        $this->assertDatabaseCount('transaction_performers', 0);
    }

    public function test_purge_without_force_changes_nothing(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $product = $this->makeProduct(10);
        $this->sell($product, 3);

        $this->artisan('clinic:purge-transactions', [
            '--tenant' => $this->tenant->slug,
        ])->assertSuccessful();

        $this->assertSame(1, Transaction::count());
        $this->assertSame(7, $product->fresh()->stock_balance);
    }

    public function test_purge_can_stop_at_a_date(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $product = $this->makeProduct(10);

        $old = $this->sell($product, 1, '2026-05-02');
        $recent = $this->sell($product, 1, '2026-07-02');

        $this->artisan('clinic:purge-transactions', [
            '--tenant' => $this->tenant->slug,
            '--before' => '2026-06-01',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertNull(Transaction::withTrashed()->find($old->id));
        $this->assertNotNull(Transaction::withTrashed()->find($recent->id));
        // Satu penjualan tersisa, jadi saldonya 10 - 1.
        $this->assertSame(9, $product->fresh()->stock_balance);
    }

    public function test_purge_leaves_another_clinic_untouched(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $product = $this->makeProduct(10);
        $this->sell($product, 1);

        $other = $this->createTenant('klinik-lain');
        app()->instance('tenant', $this->tenant);

        $this->artisan('clinic:purge-transactions', [
            '--tenant' => $other->slug,
            '--force' => true,
        ])->assertSuccessful();

        // Perintahnya mengikat tenant sasaran; kembalikan sebelum menghitung.
        app()->instance('tenant', $this->tenant);

        $this->assertSame(1, Transaction::count());
    }

    /**
     * Saldo dinaikkan lewat mutasi masuk, bukan disetel langsung: produk di
     * aplikasi ini selalu mulai dari nol dan tumbuh lewat mutasi, dan
     * perhitungan ulang saldo bersandar pada riwayat itu.
     */
    private function makeProduct(int $stock): Product
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sunscreen',
            'unit' => 'pcs',
            'price' => 100_000,
            'stock_balance' => 0,
            'status' => 'active',
        ]);

        app(StockService::class)->adjust(
            $product->refresh(),
            StockMovementType::In,
            $stock,
        );

        return $product->refresh();
    }

    private function sell(Product $product, int $qty, ?string $date = null): Transaction
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $id = $this->postJson($this->tenantUrl('transactions'), array_filter([
            'patient_id' => $patient->id,
            'issued_at' => $date ? $date.' 10:00:00' : null,
            'performer_ids' => [auth()->id()],
            'items' => [
                ['product_id' => $product->id, 'qty' => $qty],
                ['service_id' => $service->id, 'qty' => 1],
            ],
        ]))->assertCreated()->json('data.id');

        return Transaction::findOrFail($id);
    }
}
