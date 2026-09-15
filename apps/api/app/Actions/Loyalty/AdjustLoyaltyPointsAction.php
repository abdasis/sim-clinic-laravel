<?php

namespace App\Actions\Loyalty;

use App\Actions\LogAuditAction;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Ubah saldo poin loyalitas satu pasien, terkunci dan tercatat.
 *
 * Satu pintu untuk menambah (nota lunas) maupun menarik kembali (nota
 * dibatalkan) — supaya keduanya melewati penguncian dan batas bawah nol yang
 * sama, bukan dua jalur yang bisa diam-diam berbeda perilaku.
 */
class AdjustLoyaltyPointsAction
{
    /**
     * @param  int  $delta  positif menambah, negatif menarik kembali
     * @return int saldo setelah perubahan
     */
    public function handle(Patient $patient, int $delta, string $reason, ?Model $related = null): int
    {
        if ($delta === 0) {
            return $patient->loyalty_points;
        }

        [$oldBalance, $newBalance] = DB::transaction(function () use ($patient, $delta): array {
            // Baris dikunci lebih dulu supaya dua mutasi bersamaan (nota lunas
            // dan nota dibatalkan di saat yang sama) tidak menghitung saldo
            // dari angka yang sama.
            $locked = Patient::whereKey($patient->id)->lockForUpdate()->first();
            $oldBalance = $locked->loyalty_points;

            // Tidak pernah minus: pembatalan yang menarik lebih banyak dari
            // yang tersisa (poinnya sudah dipakai lebih dulu) berhenti di
            // nol, bukan saldo negatif yang membingungkan di layar pasien.
            $newBalance = max(0, $oldBalance + $delta);

            $locked->update(['loyalty_points' => $newBalance]);

            return [$oldBalance, $newBalance];
        });

        app(LogAuditAction::class)->handle(
            $delta > 0 ? 'loyalty.points_earned' : 'loyalty.points_reclaimed',
            $related ?? $patient,
            auth()->user(),
            [
                'old' => ['loyalty_points' => $oldBalance],
                'new' => ['loyalty_points' => $newBalance],
            ],
            $delta > 0
                ? $patient->name.' mendapat '.$delta.' poin loyalitas ('.$reason.'). Saldo sekarang '.$newBalance.'.'
                : $patient->name.' kehilangan '.abs($delta).' poin loyalitas ('.$reason.'). Saldo sekarang '.$newBalance.'.',
        );

        return $newBalance;
    }
}
