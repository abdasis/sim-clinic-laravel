<?php

namespace Tests\Feature\MedicalRecord;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Produk yang dibeli pasien sampai ke rekam medisnya.
 *
 * Kolom OBT/HCP dulu dibaca lewat `rekam medis → booking → transaksi`, jadi
 * produk hanya muncul kalau kasir kebetulan memilih booking saat menjual.
 * Padahal kasir hanya wajib memilih pasien, dan catatan walk-in memang tidak
 * punya booking — kolomnya pasti kosong selamanya.
 *
 * Yang dijaga di sini bukan sekadar tampilnya: dokter memutuskan boleh
 * tidaknya sebuah tindakan dari skincare yang sedang dipakai pasien, jadi
 * daftar itu tidak boleh bergantung pada ingatan kasir menekan satu kolom
 * opsional.
 */
class PatientPurchaseHistoryTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();
        $this->patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ani Lestari',
        ]);
    }

    private function makeRecord(string $date, ?Booking $booking = null): MedicalRecord
    {
        $record = MedicalRecord::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $this->patient->id,
            'booking_id' => $booking?->id,
            'author_id' => auth()->id(),
            'anamnesis' => 'Kulit kering',
        ]);

        // Catatan tanpa booking memakai waktu tulisnya sebagai tanggal
        // kunjungan, jadi itu yang perlu digeser di uji ini.
        $record->forceFill(['created_at' => $date.' 09:00:00'])->save();

        return $record->fresh();
    }

    private function makeBooking(string $date): Booking
    {
        return Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $this->patient->id,
            'service_id' => Service::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'assignee_id' => auth()->id(),
            'start_at' => $date.' 09:00:00',
            'end_at' => $date.' 10:00:00',
            'status' => 'done',
        ]);
    }

    private function sellProduct(
        string $date,
        string $name = 'Serum Vitamin C',
        ?Booking $booking = null,
        int $qty = 1,
    ): Transaction {
        $transaction = Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $this->patient->id,
            'booking_id' => $booking?->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-'.uniqid(),
            'subtotal' => 250000 * $qty,
            'paid_amount' => 250000 * $qty,
            'payment_status' => PaymentStatus::Paid,
            'issued_at' => $date.' 11:00:00',
        ]);

        $transaction->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => Product::factory()->create([
                'tenant_id' => $this->tenant->id,
                'name' => $name,
            ])->id,
            'name' => $name,
            'unit_price' => 250000,
            'qty' => $qty,
            'subtotal' => 250000 * $qty,
        ]);

        return $transaction;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function records(): array
    {
        return $this->getJson(
            $this->tenantUrl("patients/{$this->patient->id}/medical-records"),
        )->assertOk()->json('data');
    }

    /**
     * @return array<int, string>
     */
    private function productNames(array $record): array
    {
        return collect($record['transaction']['items'] ?? [])
            ->where('kind', 'product')
            ->pluck('name')
            ->all();
    }

    /**
     * Inti permintaannya: beli tanpa memilih booking, tetap sampai ke rekam
     * medis hari itu.
     */
    public function test_a_purchase_without_a_booking_reaches_that_days_record(): void
    {
        $this->makeRecord('2026-08-20');
        $this->sellProduct('2026-08-20');

        $this->assertSame(['Serum Vitamin C'], $this->productNames($this->records()[0]));
    }

    /** Catatan walk-in — yang dulu pasti kosong — kini ikut terisi. */
    public function test_a_walk_in_record_shows_its_purchases(): void
    {
        $record = $this->makeRecord('2026-08-20');
        $this->sellProduct('2026-08-20', 'Sunscreen SPF 50');

        $this->assertNull($record->booking_id);
        $this->assertSame(['Sunscreen SPF 50'], $this->productNames($this->records()[0]));
    }

    /** Nota yang menyebut booking tetap menempel ke catatan booking itu. */
    public function test_a_purchase_tied_to_a_booking_lands_on_its_record(): void
    {
        $booking = $this->makeBooking('2026-08-20');
        $this->makeRecord('2026-08-20', $booking);
        $this->sellProduct('2026-08-20', 'Toner Calming', booking: $booking);

        $this->assertSame(['Toner Calming'], $this->productNames($this->records()[0]));
    }

    /** Belanja hari lain tidak boleh bocor ke kunjungan yang bukan harinya. */
    public function test_a_purchase_from_another_day_does_not_leak(): void
    {
        $this->makeRecord('2026-08-20');
        $this->sellProduct('2026-08-25', 'Beli Kapan-kapan');

        $this->assertSame([], $this->productNames($this->records()[0]));
    }

    /**
     * Satu nota tidak boleh terhitung dua kali. Pasien yang punya dua catatan
     * di hari yang sama akan menampilkan belanja yang sama di keduanya kalau
     * penautannya hanya mencocokkan tanggal tanpa memilih pemenang.
     */
    public function test_one_purchase_is_never_counted_on_two_records(): void
    {
        $this->makeRecord('2026-08-20');
        $this->makeRecord('2026-08-20');
        $this->sellProduct('2026-08-20');

        $records = $this->records();

        $this->assertCount(2, $records);
        $this->assertSame(['Serum Vitamin C'], $this->productNames($records[0]));
        $this->assertSame([], $this->productNames($records[1]));
    }

    /** Nota batal tidak pernah sampai ke rekam medis. */
    public function test_a_cancelled_sale_is_not_recorded_as_used(): void
    {
        $this->makeRecord('2026-08-20');
        $this->sellProduct('2026-08-20')->update(['cancelled_at' => now()]);

        $this->assertSame([], $this->productNames($this->records()[0]));
    }

    /** Belanja pasien lain tidak boleh ikut terbaca. */
    public function test_another_patients_purchase_stays_out(): void
    {
        $this->makeRecord('2026-08-20');

        $other = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
        Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $other->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-LAIN',
            'subtotal' => 100000,
            'paid_amount' => 100000,
            'payment_status' => PaymentStatus::Paid,
            'issued_at' => '2026-08-20 11:00:00',
        ])->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => Product::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'name' => 'Punya Orang Lain',
            'unit_price' => 100000,
            'qty' => 1,
            'subtotal' => 100000,
        ]);

        $this->assertSame([], $this->productNames($this->records()[0]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function purchases(): array
    {
        return $this->getJson(
            $this->tenantUrl("patients/{$this->patient->id}/purchases"),
        )->assertOk()->json('data');
    }

    /**
     * Riwayat pembelian berdiri sendiri: pembelian yang tidak berpapasan
     * dengan kunjungan mana pun tetap harus terbaca dokter.
     */
    public function test_the_purchase_history_keeps_sales_with_no_visit(): void
    {
        $this->sellProduct('2026-08-25', 'Beli Tanpa Kunjungan');

        $purchases = $this->purchases();

        $this->assertCount(1, $purchases);
        $this->assertSame('Beli Tanpa Kunjungan', $purchases[0]['items'][0]['name']);
        $this->assertFalse($purchases[0]['linked_to_visit']);
    }

    /** Terbaru dulu: yang sedang dipakai pasien sekarang ada di paling atas. */
    public function test_the_purchase_history_is_newest_first(): void
    {
        $this->sellProduct('2026-08-20', 'Lama');
        $this->sellProduct('2026-08-25', 'Baru');

        $names = collect($this->purchases())
            ->map(fn (array $row) => $row['items'][0]['name'])
            ->all();

        $this->assertSame(['Baru', 'Lama'], $names);
    }

    /** Nota berisi tindakan saja tidak muncul di riwayat pembelian produk. */
    public function test_a_service_only_sale_is_not_a_product_purchase(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $this->patient->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-JASA',
            'subtotal' => 300000,
            'paid_amount' => 300000,
            'payment_status' => PaymentStatus::Paid,
            'issued_at' => '2026-08-20 11:00:00',
        ])->items()->create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $service->id,
            'name' => $service->name,
            'unit_price' => 300000,
            'qty' => 1,
            'subtotal' => 300000,
        ]);

        $this->assertSame([], $this->purchases());
    }

    /** Jumlah beli lebih dari satu ikut terbawa apa adanya. */
    public function test_quantity_survives_into_the_record(): void
    {
        $this->makeRecord('2026-08-20');
        $this->sellProduct('2026-08-20', qty: 3);

        $item = collect($this->records()[0]['transaction']['items'])->firstWhere('kind', 'product');

        $this->assertSame(3, $item['qty']);
        $this->assertEqualsWithDelta(750000.0, $item['subtotal'], 0.01);
    }
}
