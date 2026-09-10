<?php

namespace Tests\Feature\Transaction;

use App\Models\CompanyProfileSetting;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Isi nota PDF thermal, bukan cuma header responsnya.
 *
 * Kertas 58mm habis per milimeter, dan tes lain hanya memastikan berkasnya
 * terunduh. Yang dijaga di sini justru yang membuat gulungannya boros:
 * tautan peta yang ikut tercetak, dan keterangan cetak yang mengulang
 * tanggal di kepala nota.
 *
 * Blade-nya dirender jadi HTML, bukan PDF: isinya yang diuji, dan biner PDF
 * tidak bisa dibaca ulang tanpa memasang pustaka pembaca hanya demi tes.
 */
class ReceiptPdfLayoutTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function makeTransaction(int $printCount = 0): Transaction
    {
        return Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'cashier_id' => auth()->id(),
            'subtotal' => 250000,
            'print_count' => $printCount,
        ]);
    }

    private function render(Transaction $transaction): string
    {
        return view('receipt-pdf', app(InvoiceService::class)->render($transaction))->render();
    }

    /** Tautan peta di atas kertas memakan dua baris dan tidak bisa diklik siapa pun. */
    public function test_the_printed_address_carries_no_map_link(): void
    {
        $this->actingAsClinicUser();

        CompanyProfileSetting::create([
            'tenant_id' => $this->tenant->id,
            'address' => 'Jl Ringroad Blok A 10, Medan Selayang https://maps.app.goo.gl/5MwdHVGJ6E',
        ]);

        $html = $this->render($this->makeTransaction());

        $this->assertStringNotContainsString('maps.app.goo.gl', $html);
        $this->assertStringContainsString('Jl Ringroad Blok A 10, Medan Selayang', $html);
    }

    /**
     * Pada cetakan pertama, keterangan cetak cuma mengulang tanggal yang sudah
     * ada di kepala nota — tiga baris di tiap struk yang tidak pernah dibaca.
     */
    public function test_a_first_print_carries_no_print_note(): void
    {
        $this->actingAsClinicUser();

        $html = $this->render($this->makeTransaction());

        $this->assertStringNotContainsString(__('invoice.reprint'), $html);
    }

    /**
     * Cetakan kedua justru wajib ditandai: tanpa itu satu transaksi bisa
     * beredar sebagai dua bukti bayar yang sama sahnya.
     */
    public function test_a_reprint_is_marked_with_its_number_and_time(): void
    {
        $this->actingAsClinicUser();

        $html = $this->render($this->makeTransaction(printCount: 3));

        $this->assertStringContainsString(__('invoice.reprint').' #3', $html);
        $this->assertStringContainsString(now()->format('d/m/Y H:i'), $html);
    }

    /** Tambahkan baris layanan bernama panjang — yang paling banyak membungkus. */
    private function withItems(Transaction $transaction, int $count): Transaction
    {
        foreach (range(1, $count) as $i) {
            $service = Service::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Perawatan Wajah Lengkap Plus Serum Nomor '.$i,
                'price' => 125000,
                'duration_minutes' => 60,
                'status' => 'active',
            ]);

            TransactionItem::create([
                'tenant_id' => $this->tenant->id,
                'transaction_id' => $transaction->id,
                'service_id' => $service->id,
                'name' => $service->name,
                'qty' => 1,
                'unit_price' => 125000,
                'subtotal' => 125000,
            ]);
        }

        return $transaction->fresh();
    }

    /**
     * Nota harus selalu jadi satu potong kertas.
     *
     * Kertas gulungan dipotong di ujung cetakan, jadi nota yang tumpah ke
     * halaman kedua keluar sebagai dua potong — dan yang kedua berisi
     * potongan totalnya saja, tanpa kepala nota. Rumus lama (tinggi tetap
     * 600pt) sudah tumpah sejak sepuluh baris item.
     *
     * @dataProvider itemCounts
     */
    #[DataProvider('itemCounts')]
    public function test_the_receipt_always_fits_on_one_page(int $itemCount): void
    {
        $this->actingAsClinicUser();

        $transaction = $this->withItems($this->makeTransaction(), $itemCount);

        $pdf = $this->get($this->tenantUrl("transactions/{$transaction->id}/invoice/pdf"))
            ->assertOk()
            ->getContent();

        $pages = substr_count($pdf, '/Type /Page') - substr_count($pdf, '/Type /Pages');

        $this->assertSame(1, $pages, "nota {$itemCount} item tumpah ke halaman kedua");
    }

    /** @return array<string, array{int}> */
    public static function itemCounts(): array
    {
        return [
            '1 item' => [1],
            '5 item' => [5],
            '10 item' => [10],
            '15 item' => [15],
        ];
    }
}
