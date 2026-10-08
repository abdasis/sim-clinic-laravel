<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\InvoiceService;
use App\Support\ReceiptAddress;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class InvoiceController extends Controller
{
    public function show(Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        return response()->view('invoice', app(InvoiceService::class)->render($transaction));
    }

    public function pdf(Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        $data = app(InvoiceService::class)->render($transaction);

        return Pdf::loadView('receipt-pdf', $data)
            ->setPaper([0, 0, self::PAPER_WIDTH, $this->paperHeight($data)], 'portrait')
            ->download($transaction->invoice_number.'.pdf');
    }

    /**
     * Lebar halaman nota: 48mm dalam poin.
     *
     * Disamakan dengan area cetak kepala termal (384 titik pada 203dpi),
     * bukan dengan lebar kertasnya (57mm). Halaman 57mm yang dikirim ke
     * printer yang cuma bisa mencetak 48mm tidak ditolak — drivernya
     * mengecilkan seluruh halaman supaya muat, dan notanya tercetak lebih
     * kecil dari yang dirancang tanpa ada yang tampak salah di layar.
     */
    private const PAPER_WIDTH = 136.06;

    /**
     * Kelebihan tinggi yang sengaja disisakan.
     *
     * Taksiran di bawah menghitung baris, bukan merender font: DomPDF bisa
     * membungkus satu baris lebih cepat dari dugaan, dan selisih satu baris
     * saja sudah cukup menumpahkan nota ke halaman kedua. Kertas yang lebih
     * panjang 2cm jauh lebih murah daripada nota yang keluar dua potong.
     *
     * Besarnya diukur, bukan ditebak: tinggi minimum yang masih satu halaman
     * bergoyang sampai ~41pt antar nota dengan isi sama — nomor nota dan
     * tanggal punya lebar berbeda, dan letak potongan halaman ikut bergeser.
     * Angka ini menutup goyangan itu berikut satu baris cadangan.
     */
    private const BUFFER = 44.0;

    /**
     * Berapa huruf nama item yang muat dalam satu baris.
     *
     * Diukur, bukan ditaksir dari lebar font: nota dirender berulang dengan
     * nama yang makin panjang sambil mencari tinggi terkecil yang masih satu
     * halaman. Nama 23 huruf masih memakan tinggi yang sama dengan nama 10
     * huruf, sedangkan 41 huruf memakan dua kali lipat. Angka di sini ditahan
     * sedikit di bawah batas terukur supaya nama yang mepet tetap dihitung
     * membungkus.
     */
    private const NAME_CHARS_PER_LINE = 22;

    /** Tinggi satu baris item bernama pendek: nama, "jumlah x harga", dan jeda. */
    private const ITEM_HEIGHT = 32.0;

    /** Tambahan tinggi tiap kali nama item membungkus satu baris lagi. */
    private const ITEM_WRAP_HEIGHT = 30.0;

    /**
     * Tinggi kertas nota, mengikuti isinya.
     *
     * Kertas gulungan tidak punya ukuran tetap: yang dicetak sepanjang yang
     * ditulis. Tinggi tetap membuat nota dua baris tetap memakan satu lembar
     * penuh — dan pada saat yang sama tidak cukup untuk nota sepuluh baris,
     * yang diam-diam tumpah ke halaman kedua dan tercetak sebagai dua potong
     * kertas, yang kedua tanpa kepala nota.
     *
     * Rumus tetap (base + item x angka tetap) selalu salah di salah satu
     * ujungnya: angka yang cukup untuk nama dua baris memboroskan ~3cm di tiap
     * nota bernama pendek, sementara angka yang pas untuk nama pendek
     * menumpahkan nota bernama panjang. Karena itu tiap bagian ditaksir dari
     * isinya sendiri — nama yang membungkus dihitung dua baris, dan bagian
     * yang hanya kadang hadir (tagline, alamat, catatan, pelaksana, sisa
     * bayar, penanda cetak ulang) baru menambah tinggi saat benar-benar ada.
     *
     * ponytail: ini tetap taksiran, bukan pengukuran render. Tinggi baris
     * sebenarnya bergantung pada font yang dipakai DomPDF dan pembulatannya,
     * jadi hasilnya dilebihkan sedikit lewat BUFFER. Ganti dengan pengukuran
     * render sungguhan bila suatu saat butuh presisi milimeter; untuk sekarang
     * yang dikejar cuma menghapus pemborosan besar tanpa pernah tumpah —
     * dijaga ReceiptPdfLayoutTest, yang menghitung halaman PDF-nya sungguhan.
     *
     * @param  array<string, mixed>  $data  hasil InvoiceService::render()
     */
    private function paperHeight(array $data): float
    {
        $transaction = $data['transaction'];
        $profile = $data['tenant']?->companyProfile;

        // Kop, pita judul, tabel keterangan, blok total, dan penutup — bagian
        // yang selalu ada berapa pun isinya. Diukur dari nota satu item
        // bernama pendek tanpa profil: 282pt, dikurangi item dan judul
        // kelompoknya.
        $height = 236.0;

        foreach ($data['items'] as $item) {
            $height += $this->itemHeight((string) $item->name);
        }

        // Judul kelompok "LAYANAN / TINDAKAN" dan "PRODUK": muncul hanya untuk
        // kelompok yang berisi, jadi nota tanpa produk tidak membayar judulnya.
        $height += 14 * $this->groupCount($data['items']);

        $height += 14 * $data['payments']->count();

        if ($data['payments']->count() > 1) {
            $height += 14; // baris "Sudah Dibayar" baru muncul saat bertahap
        }

        if ((float) $transaction->outstandingAmount() > 0) {
            $height += 20; // kotak sisa bayar, berbingkai
        }

        if ((int) ($transaction->points_earned ?? 0) > 0) {
            $height += 12;
        }

        if ((int) ($transaction->points_redeemed ?? 0) > 0) {
            $height += 12;
        }

        if ((int) ($transaction->print_count ?? 1) > 1) {
            $height += 18; // penanda cetak ulang, berbingkai
        }

        if ($transaction->relationLoaded('performers') && $transaction->performers->isNotEmpty()) {
            $height += 12;
        }

        if ($transaction->cancelled_at !== null) {
            $height += 26; // pita "dibatalkan" berikut keterangannya
        }

        // Angka ketiga blok di bawah diukur satu per satu, bukan ditaksir.
        // Masing-masing lebih mahal daripada tinggi barisnya sendiri karena
        // kehadirannya menggeser letak potongan halaman. Digabung, ketiganya
        // lebih murah daripada jumlahnya — tapi yang dipakai di sini angka
        // sendiri-sendirinya, supaya nota yang cuma punya satu di antaranya
        // tetap aman. Nota berprofil lengkap jadi kelebihan ~1,5cm; itu harga
        // yang jauh lebih murah daripada nota yang keluar dua potong.
        if (filled($profile?->tagline)) {
            $height += 40;
        }

        if (filled($profile?->receipt_note)) {
            $height += 44;
        }

        $address = ReceiptAddress::format($profile?->address);

        if (filled($address)) {
            // Alamat rata tengah dan boleh membungkus; 7.5pt memuat ~30 huruf.
            $height += 18 * max(1, (int) ceil(mb_strlen($address) / 30));
        }

        return $height + self::BUFFER;
    }

    /**
     * Tinggi satu baris item: nama di atas, "jumlah x harga" dan nominal di
     * bawahnya. Nama yang tidak muat satu baris membungkus dan menambah tinggi.
     */
    private function itemHeight(string $name): float
    {
        $lines = max(1, (int) ceil(mb_strlen($name) / self::NAME_CHARS_PER_LINE));

        return self::ITEM_HEIGHT + (self::ITEM_WRAP_HEIGHT * ($lines - 1));
    }

    /**
     * Berapa kelompok yang benar-benar punya isi — layanan, produk, atau
     * keduanya. Judul kelompok kosong tidak pernah dicetak.
     *
     * @param  Collection<int, TransactionItem>  $items
     */
    private function groupCount($items): int
    {
        $hasProduct = $items->contains(fn ($item) => $item->service_id === null);
        $hasService = $items->contains(fn ($item) => $item->service_id !== null);

        return (int) $hasProduct + (int) $hasService;
    }

    /**
     * Dipanggil tepat sebelum dialog cetak dibuka, sehingga nomor cetakan yang
     * tercetak di kertas adalah nomor yang baru saja disimpan.
     */
    public function recordPrint(Transaction $transaction, InvoiceService $invoices): JsonResponse
    {
        $this->authorize('view', $transaction);

        $invoices->recordPrint($transaction);

        return response()->json([
            'data' => new TransactionResource($transaction->fresh()),
            'meta' => [],
        ]);
    }
}
