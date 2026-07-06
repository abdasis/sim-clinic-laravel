<?php

namespace App\Services;

use App\Models\Transaction;

/**
 * Render data invoice (R4): konten diambil dari transaction + relasi,
 * bukan kolom duplikat. Dipakai controller untuk view HTML print.
 */
class InvoiceService
{
    public function render(Transaction $transaction): array
    {
        $transaction->loadMissing('items', 'payments', 'patient', 'cashier', 'invoice');

        $tenant = app()->bound('tenant') ? app('tenant') : null;

        return [
            'tenant' => $tenant,
            'patient' => $transaction->patient,
            'cashier' => $transaction->cashier,
            'items' => $transaction->items,
            'payments' => $transaction->payments,
            'subtotal' => $transaction->subtotal,
            'invoice_number' => $transaction->invoice_number,
            'issued_at' => $transaction->invoice?->issued_at ?? $transaction->created_at,
        ];
    }
}
