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
        $this->guardUncancelled($transaction);

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

    /**
     * Transaksi yang belum dibatalkan tidak boleh langsung dihapus bila sudah
     * meninggalkan jejak.
     *
     * Menghapus menyembunyikan barisnya dari seluruh kueri, termasuk laporan.
     * Untuk transaksi yang sudah dibayar, pemasukannya lenyap dari laporan
     * tanpa ada yang mencatat ke mana. Untuk transaksi berisi produk, stoknya
     * tetap terpotong padahal penjualannya sudah tidak ada — dan hanya
     * pembatalan yang mengembalikannya.
     *
     * Membatalkan lebih dulu menyelesaikan keduanya, jadi itu yang diminta.
     */
    private function guardUncancelled(Transaction $transaction): void
    {
        if ($transaction->cancelled_at !== null) {
            return;
        }

        $hasPayment = (float) $transaction->paid_amount > 0
            || $transaction->payments()->exists();

        $hasStockImpact = $transaction->items()->whereNotNull('product_id')->exists();

        abort_if(
            $hasPayment || $hasStockImpact,
            422,
            __('pos.delete_requires_cancel'),
        );
    }
}
