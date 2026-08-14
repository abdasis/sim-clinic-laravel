<?php

namespace App\Actions\Transaction;

use App\Actions\LogAuditAction;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hapus transaksi dari daftar aktif tanpa membuang jejaknya — riwayat kas
 * tetap dapat diaudit dan nomor invoicenya tidak dipakai ulang.
 */
class SoftDeleteTransactionAction
{
    public function handle(Transaction $transaction): Transaction
    {
        try {
            $transaction->delete();
        } catch (Throwable $e) {
            Log::error('Gagal menghapus transaksi.', [
                'exception' => $e,
                'transaction_id' => $transaction->id,
                'tenant_id' => $transaction->tenant_id,
            ]);

            throw $e;
        }

        app(LogAuditAction::class)->handle(
            'pos.transaction.deleted',
            $transaction,
            auth()->user(),
            [
                'invoice_number' => $transaction->invoice_number,
                'old' => ['deleted_at' => null],
                'new' => ['deleted_at' => $transaction->deleted_at?->toIso8601String()],
            ],
            'Menghapus transaksi '.$transaction->invoice_number.'.',
        );

        return $transaction;
    }
}
