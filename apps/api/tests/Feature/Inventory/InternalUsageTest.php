<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Stok yang habis dipakai klinik sendiri, dipisah dari yang rusak atau hilang.
 *
 * Sebelumnya semua barang yang keluar tanpa terjual masuk satu keranjang
 * "Stok Keluar", jadi masker yang habis dipakai terapis tidak terpisah dari
 * botol yang pecah. Keduanya berbeda arti: yang terpakai treatment adalah
 * biaya layanan, yang rusak adalah kerugian — dan biaya bahan per periode
 * tidak bisa dihitung selama keduanya menyatu.
 */
class InternalUsageTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Masker Sheet',
            'type' => 'consumable',
            'stock_balance' => 100,
        ]);
    }

    private function record(string $type, int $quantity, string $note = 'dipakai treatment')
    {
        return $this->postJson($this->tenantUrl("products/{$this->product->id}/stock-movements"), [
            'type' => $type,
            'quantity' => $quantity,
            'note' => $note,
        ]);
    }

    /** Inti permintaannya: pemakaian sendiri bisa dicatat. */
    public function test_internal_usage_can_be_recorded(): void
    {
        $this->record('used_internal', 5)->assertCreated();

        $movement = StockMovement::query()->latest('id')->first();

        $this->assertSame(StockMovementType::UsedInternal, $movement->type);
        $this->assertSame(5, $movement->quantity);
    }

    /** Dan benar-benar mengurangi saldo, bukan sekadar tercatat. */
    public function test_internal_usage_reduces_the_balance(): void
    {
        $this->record('used_internal', 5)->assertCreated();

        $this->assertSame(95, $this->product->fresh()->stock_balance);
    }

    /**
     * Terpisah dari stok keluar biasa — itu seluruh gunanya. Kalau keduanya
     * menyatu, klinik tetap tidak bisa memisahkan biaya bahan dari kerugian.
     */
    public function test_internal_usage_is_distinct_from_a_plain_stock_out(): void
    {
        $this->record('used_internal', 5)->assertCreated();
        $this->record('out_manual', 3, 'botol pecah')->assertCreated();

        $this->assertSame(
            5,
            (int) StockMovement::query()
                ->where('type', StockMovementType::UsedInternal)
                ->sum('quantity'),
        );
        $this->assertSame(
            3,
            (int) StockMovement::query()
                ->where('type', StockMovementType::OutManual)
                ->sum('quantity'),
        );
    }

    /** Stok fisik tetap tidak boleh minus lewat jalur ini. */
    public function test_internal_usage_cannot_push_the_balance_negative(): void
    {
        $this->record('used_internal', 500)->assertStatus(422);

        $this->assertSame(100, $this->product->fresh()->stock_balance);
    }

    /** Jenis karangan tetap ditolak sebagai galat formulir. */
    public function test_an_unknown_movement_type_is_rejected(): void
    {
        $this->record('entah', 5)->assertStatus(422)->assertJsonValidationErrors('type');
    }

    /** Arahnya keluar, jadi tidak boleh terhitung sebagai barang masuk. */
    public function test_internal_usage_counts_as_outbound(): void
    {
        $this->assertFalse(StockMovementType::UsedInternal->isInbound());
    }

    /** Ringkasan inventaris memisahkannya sebagai angka tersendiri. */
    public function test_the_inventory_stats_report_internal_usage_separately(): void
    {
        $this->record('used_internal', 5)->assertCreated();
        $this->record('out_manual', 3, 'botol pecah')->assertCreated();

        $kpis = collect(
            $this->getJson($this->tenantUrl('stats/inventory'))->assertOk()->json('data.kpis'),
        )->keyBy('key');

        $this->assertSame(5, $kpis['used_internal']['value']);
        // Total keluar tetap menghitung keduanya: pemakaian sendiri memang
        // barang yang keluar, hanya sebabnya yang berbeda.
        $this->assertSame(8, $kpis['total_out']['value']);
    }

    /** Labelnya terbaca, bukan nilai enum mentah. */
    public function test_the_type_has_a_readable_label(): void
    {
        $this->assertSame('Dipakai Sendiri', StockMovementType::UsedInternal->label());
    }
}
