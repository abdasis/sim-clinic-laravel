<?php

namespace App\Support;

use App\Models\BroadcastRecipient;

/**
 * Riwayat pengingat yang sudah diterima seorang pasien.
 *
 * Klinik menjalankan dua mesin pengingat: aturan per layanan dan follow-up
 * otomatis dari rekam medis. Keduanya berangkat dari kunjungan yang sama, dan
 * bila masing-masing hanya memeriksa jejaknya sendiri, pasien menerima dua
 * pesan pada pagi yang sama untuk satu kunjungan.
 *
 * Aturannya satu dan tinggal di sini: satu kunjungan cukup satu pengingat,
 * siapa pun yang mengirimnya. Pasien yang datang lagi memulai putaran baru
 * karena pembandingnya waktu kunjungan terakhir.
 */
class ReminderHistory
{
    public static function remindedSince(int $patientId, string $lastAt): bool
    {
        return BroadcastRecipient::query()
            ->where('patient_id', $patientId)
            ->where('created_at', '>', $lastAt)
            ->where(fn ($query) => $query
                // Pengingat dari aturan per layanan menandai barisnya sendiri;
                // follow-up otomatis ditandai di broadcast-nya.
                ->whereNotNull('reminder_rule_id')
                ->orWhereHas('broadcast', fn ($q) => $q->where('audience_params->auto', true)))
            ->exists();
    }
}
