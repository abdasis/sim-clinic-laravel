<?php

namespace App\Services;

use App\Actions\Transaction\PayTransactionAction;
use App\Enums\PaymentStatus;
use App\Models\Transaction;

/**
 * Pencatatan pembayaran satu nota (FR-055). Pasien kerap membayar bertahap,
 * jadi satu transaksi bisa menerima beberapa kali setoran sampai lunas.
 *
 * Tipis memang — orkestrasinya baru satu Action. Tetap ada supaya controller
 * punya satu pintu masuk yang sama dengan modul lain, dan supaya langkah
 * berikutnya (potong komisi, kirim struk digital) menempel di sini, bukan
 * menumpuk di controller.
 */
class PaymentService
{
    public function __construct(private readonly PayTransactionAction $pay) {}

    /**
     * @param  array{method:string, amount:float|string, paid_at:mixed}  $data
     * @return array{payment_status:PaymentStatus, overpaid:bool, paid_amount:float, outstanding:float}
     */
    public function record(Transaction $transaction, array $data): array
    {
        return $this->pay->handle($transaction, $data);
    }
}
