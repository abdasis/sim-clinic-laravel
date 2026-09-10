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

        // ponytail: tinggi dasar 600pt (~21cm) menampung nota kasir standar; diperlebar dinamis bila item melebihi 10 baris agar tidak terpotong ke halaman baru.
        $itemCount = $transaction->items->count();
        $height = max(600, 300 + ($itemCount * 30));

        return Pdf::loadView('receipt-pdf', $data)
            ->setPaper([0, 0, 163.28, $height], 'portrait')
            ->download($transaction->invoice_number.'.pdf');
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
