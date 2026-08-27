<?php

namespace Tests\Feature\Transaction;

use App\Enums\DiscountType;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Potongan harga di tingkat nota, di luar promo yang menempel per barang.
 *
 * Promo menjawab "layanan ini sedang diskon"; yang belum terjawab adalah
 * potongan yang diputuskan di meja kasir. Selama ini kasir menyiasatinya
 * dengan mengubah harga satuan, yang membuat harga asli layanan ikut hilang
 * dari nota dan laporan.
 */
class PosDiscountTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private Service $service;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();
        $this->patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
            'price' => 200000,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $discount
     */
    private function checkout(array $discount = [], int $qty = 1)
    {
        return $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $this->patient->id,
            'items' => [[
                'service_id' => $this->service->id,
                'qty' => $qty,
            ]],
            ...$discount,
        ]);
    }

    public function test_a_percentage_discount_reduces_what_is_payable(): void
    {
        $this->checkout(['discount_type' => 'percent', 'discount_value' => 10])
            ->assertCreated();

        $transaction = Transaction::query()->latest('id')->first();

        $this->assertEqualsWithDelta(200000.0, (float) $transaction->items_total, 0.01);
        $this->assertEqualsWithDelta(20000.0, (float) $transaction->discount_amount, 0.01);
        $this->assertEqualsWithDelta(180000.0, (float) $transaction->subtotal, 0.01);
    }

    /** Persen pecahan ikut didukung — 70,5% adalah angka yang wajar. */
    public function test_a_fractional_percentage_is_supported(): void
    {
        $this->checkout(['discount_type' => 'percent', 'discount_value' => 70.5])
            ->assertCreated();

        $transaction = Transaction::query()->latest('id')->first();

        // 200.000 - 70,5% = 59.000
        $this->assertEqualsWithDelta(141000.0, (float) $transaction->discount_amount, 0.01);
        $this->assertEqualsWithDelta(59000.0, (float) $transaction->subtotal, 0.01);
    }

    public function test_a_fixed_discount_reduces_what_is_payable(): void
    {
        $this->checkout(['discount_type' => 'fixed', 'discount_value' => 25000])
            ->assertCreated();

        $transaction = Transaction::query()->latest('id')->first();

        $this->assertEqualsWithDelta(25000.0, (float) $transaction->discount_amount, 0.01);
        $this->assertEqualsWithDelta(175000.0, (float) $transaction->subtotal, 0.01);
    }

    /**
     * Potongan yang melebihi tagihan berhenti di gratis. Nota bernilai minus
     * akan membuat sisa tagihan negatif dan status lunas jadi tidak berarti.
     */
    public function test_a_discount_larger_than_the_bill_stops_at_free(): void
    {
        $this->checkout(['discount_type' => 'fixed', 'discount_value' => 999000])
            ->assertCreated();

        $transaction = Transaction::query()->latest('id')->first();

        $this->assertEqualsWithDelta(0.0, (float) $transaction->subtotal, 0.01);
        $this->assertEqualsWithDelta(200000.0, (float) $transaction->discount_amount, 0.01);
    }

    /** Harga asli layanan tetap utuh di barisnya, tidak ikut dipangkas. */
    public function test_the_line_keeps_its_original_price(): void
    {
        $this->checkout(['discount_type' => 'percent', 'discount_value' => 50])
            ->assertCreated();

        $item = Transaction::query()->latest('id')->first()->items()->first();

        $this->assertEqualsWithDelta(200000.0, (float) $item->unit_price, 0.01);
        $this->assertEqualsWithDelta(200000.0, (float) $item->subtotal, 0.01);
    }

    /** Nota tanpa potongan tetap seperti sebelumnya. */
    public function test_a_transaction_without_a_discount_is_unchanged(): void
    {
        $this->checkout()->assertCreated();

        $transaction = Transaction::query()->latest('id')->first();

        $this->assertNull($transaction->discount_type);
        $this->assertEqualsWithDelta(0.0, (float) $transaction->discount_amount, 0.01);
        $this->assertEqualsWithDelta(200000.0, (float) $transaction->subtotal, 0.01);
    }

    /** Sisa tagihan dihitung dari jumlah setelah potongan. */
    public function test_the_outstanding_follows_the_discounted_amount(): void
    {
        $id = $this->checkout(['discount_type' => 'percent', 'discount_value' => 10])
            ->assertCreated()->json('data.id');

        $this->postJson($this->tenantUrl("transactions/{$id}/payments"), [
            'method' => 'cash',
            'amount' => 180000,
            'paid_at' => now()->toDateTimeString(),
        ])->assertOk();

        $transaction = Transaction::query()->find($id);

        $this->assertEqualsWithDelta(0.0, $transaction->outstandingAmount(), 0.01);
        $this->assertSame('paid', $transaction->payment_status->value);
    }

    public function test_a_percentage_over_one_hundred_is_rejected(): void
    {
        $this->checkout(['discount_type' => 'percent', 'discount_value' => 150])
            ->assertStatus(422)->assertJsonValidationErrors('discount_value');
    }

    public function test_a_discount_type_without_a_value_is_rejected(): void
    {
        $this->checkout(['discount_type' => 'percent'])
            ->assertStatus(422)->assertJsonValidationErrors('discount_value');
    }

    /** Rincian potongannya ikut terbaca di layar nota. */
    public function test_the_receipt_payload_carries_the_discount(): void
    {
        $id = $this->checkout(['discount_type' => 'percent', 'discount_value' => 70.5])
            ->assertCreated()->json('data.id');

        $this->getJson($this->tenantUrl("transactions/{$id}"))
            ->assertOk()
            ->assertJsonPath('data.discount_type', 'percent')
            ->assertJsonPath('data.discount_type_label', 'Persen (%)');
    }

    /**
     * Omzet mengikuti yang benar-benar ditagih. Menjumlahkan baris nota akan
     * melaporkan lebih besar daripada uang yang masuk, karena potongan di
     * tingkat nota tidak menempel pada baris mana pun.
     */
    public function test_the_revenue_report_follows_the_discounted_amount(): void
    {
        $id = $this->checkout(['discount_type' => 'fixed', 'discount_value' => 50000])
            ->assertCreated()->json('data.id');

        $this->postJson($this->tenantUrl("transactions/{$id}/payments"), [
            'method' => 'cash',
            'amount' => 150000,
            'paid_at' => now()->toDateTimeString(),
        ])->assertOk();

        $revenue = $this->getJson(
            $this->tenantUrl('reports/revenue').'?'.http_build_query([
                'from' => now()->subDay()->toDateString(),
                'to' => now()->addDay()->toDateString(),
            ]),
        )->assertOk()->json('data');

        $this->assertEqualsWithDelta(150000.0, (float) $revenue['total_revenue'], 0.01);
    }

    /** Enum potongan dipakai bersama dengan promo, jadi labelnya sudah ada. */
    public function test_the_discount_types_are_shared_with_promos(): void
    {
        $this->assertSame('Persen (%)', DiscountType::Percent->label());
        $this->assertSame('Nominal (Rp)', DiscountType::Fixed->label());
    }

    /**
     * Nota cetak menjumlahkan potongan promo dan potongan nota jadi satu
     * baris "Potongan". Yang dijaga di sini bukan pemisahannya melainkan
     * jumlahnya: selisih harga normal dan yang ditagih harus persis sebesar
     * potongan yang diterima pasien.
     */
    public function test_the_printed_receipt_totals_reconcile(): void
    {
        $id = $this->checkout(['discount_type' => 'percent', 'discount_value' => 10])
            ->assertCreated()->json('data.id');

        $data = $this->getJson($this->tenantUrl("transactions/{$id}"))
            ->assertOk()->json('data');

        $gross = collect($data['items'])->sum(
            fn (array $item) => max((float) ($item['list_price'] ?? 0), (float) $item['unit_price'])
                * (int) $item['qty'],
        );

        $this->assertEqualsWithDelta(
            (float) $data['discount_amount'],
            $gross - (float) $data['subtotal'],
            0.01,
        );
    }
}
