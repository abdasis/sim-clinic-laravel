<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\InvoiceService;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function show(Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        return response()->view('invoice', app(InvoiceService::class)->render($transaction));
    }
}
