<?php

namespace App\Support;

/**
 * Konversi belanja ke poin loyalitas.
 *
 * Kelas baca murni, tanpa DB — dipakai sekali di titik nota menjadi lunas,
 * dan hasilnya disnapshot di kolom `points_earned` supaya perubahan tarif
 * belakangan tidak diam-diam menulis ulang riwayat.
 */
class LoyaltyPoints
{
    /** Rupiah per satu poin. */
    private const RATE = 10_000;

    /**
     * Poin dari satu nota. Sisa di bawah tarif dibuang, bukan dibulatkan —
     * Rp19.000 tetap 1 poin, bukan 2, karena belum genap Rp20.000.
     */
    public static function earn(float $amount): int
    {
        return (int) floor(max(0.0, $amount) / self::RATE);
    }
}
