<?php

namespace App\Actions\Loyalty;

use App\Models\Patient;
use App\Models\Transaction;

/**
 * Tukar poin pasien jadi potongan pada satu nota.
 *
 * Saldonya dibaca terkunci dan penukaran yang melebihinya ditolak, bukan
 * dipangkas diam-diam: kasir sudah menyebut angka potongannya ke pasien
 * sebelum menekan simpan, jadi nota yang terbit dengan potongan lebih kecil
 * dari yang dijanjikan lebih buruk daripada simpan yang gagal terang-terangan.
 *
 * Dipanggil di dalam DB transaction pembuatan nota, sehingga penolakan di sini
 * ikut membatalkan notanya — tidak ada nota yang terlanjur memotong tagihan
 * dengan poin yang ternyata tidak ada.
 */
class RedeemLoyaltyPointsAction
{
    public function handle(Transaction $transaction, int $points): void
    {
        if ($points <= 0) {
            return;
        }

        $balance = Patient::whereKey($transaction->patient_id)
            ->lockForUpdate()
            ->value('loyalty_points') ?? 0;

        abort_if(
            $points > $balance,
            422,
            __('pos.points_insufficient', ['balance' => $balance]),
        );

        app(AdjustLoyaltyPointsAction::class)->handle(
            $transaction->patient,
            -$points,
            'ditukar di nota '.$transaction->invoice_number,
            $transaction,
        );
    }
}
