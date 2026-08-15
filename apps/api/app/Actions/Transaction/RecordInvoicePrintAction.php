<?php

namespace App\Actions\Transaction;

use App\Actions\LogAuditAction;
use App\Models\Transaction;

/**
 * Catat satu kali pencetakan nota. Dipanggil sebelum dialog cetak terbuka
 * supaya nomor cetakan yang tertera di kertas sama dengan yang tersimpan.
 */
class RecordInvoicePrintAction
{
    public function handle(Transaction $transaction): Transaction
    {
        $previous = $transaction->print_count;

        $transaction->forceFill([
            'print_count' => $previous + 1,
            'last_printed_at' => now(),
        ])->save();

        app(LogAuditAction::class)->handle(
            'transaction.printed',
            $transaction,
            auth()->user(),
            [
                'old' => ['print_count' => $previous],
                'new' => [
                    'print_count' => $transaction->print_count,
                    'last_printed_at' => $transaction->last_printed_at?->toIso8601String(),
                ],
            ],
            $previous === 0
                ? 'Mencetak nota '.$transaction->invoice_number.'.'
                : 'Mencetak ulang nota '.$transaction->invoice_number.' (cetakan ke-'.$transaction->print_count.').',
        );

        return $transaction;
    }
}
