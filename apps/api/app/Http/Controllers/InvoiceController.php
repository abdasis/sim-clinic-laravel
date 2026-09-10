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
            ->setPaper([0, 0, 163.28, $this->paperHeight($transaction)], 'portrait')
            ->download($transaction->invoice_number.'.pdf');
    }

    /**
     * Tinggi kertas nota, mengikuti isinya.
     *
     * Kertas gulungan tidak punya ukuran tetap: yang dicetak sepanjang yang
     * ditulis. Tinggi tetap 600pt membuat nota dua baris tetap memakan 21cm —
     * dan pada saat yang sama tidak cukup untuk nota sepuluh baris, yang
     * diam-diam tumpah ke halaman kedua dan tercetak sebagai dua potong kertas.
     *
     * Angkanya diukur, bukan ditaksir: nota kosong butuh ~230pt dan tiap baris
     * item menambah ~43pt pada nama layanan sepanjang dua baris. Yang dipakai
     * di sini dilebihkan (50pt per item) supaya nama yang lebih panjang dari
     * itu tetap muat — dijaga tesnya di ReceiptPdfLayoutTest.
     */
    private function paperHeight(Transaction $transaction): float
    {
        return 280
            + ($transaction->items->count() * 50)
            + ($transaction->payments->count() * 14);
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
