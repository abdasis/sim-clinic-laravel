<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function show(Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        return response()->view('invoice', app(InvoiceService::class)->render($transaction));
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
