<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

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
            ->setPaper([0, 0, self::PAPER_WIDTH, $this->paperHeight($transaction)], 'portrait')
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
     * Tinggi kertas nota, mengikuti isinya.
     *
     * Kertas gulungan tidak punya ukuran tetap: yang dicetak sepanjang yang
     * ditulis. Tinggi tetap 600pt membuat nota dua baris tetap memakan 21cm —
     * dan pada saat yang sama tidak cukup untuk nota sepuluh baris, yang
     * diam-diam tumpah ke halaman kedua dan tercetak sebagai dua potong kertas.
     *
     * Angkanya diukur ulang tiap kali lebar kertas atau ukuran hurufnya
     * berubah, bukan ditaksir: pada lebar 48mm dan ukuran huruf sekarang,
     * nota kosong butuh ~280pt dan tiap baris item menambah ~63pt untuk nama
     * layanan sepanjang dua baris. Yang dipakai di sini dilebihkan (70pt per
     * item) supaya nama yang lebih panjang tetap muat — dijaga tesnya di
     * ReceiptPdfLayoutTest, yang menghitung halaman PDF-nya sungguhan.
     */
    private function paperHeight(Transaction $transaction): float
    {
        return 320
            + ($transaction->items->count() * 70)
            + ($transaction->payments->count() * 16);
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
