<?php

namespace App\Support;

/**
 * Konversi belanja ke poin loyalitas, dan poin kembali ke rupiah.
 *
 * Kelas baca murni, tanpa DB — dipakai di dua titik (nota menjadi lunas, dan
 * nota menukar poin), dan hasil keduanya disnapshot di kolom nota supaya
 * perubahan tarif belakangan tidak diam-diam menulis ulang riwayat.
 *
 * ponytail: kedua tarif masih tetap untuk semua klinik. Naikkan ke setelan per
 * tenant begitu ada klinik yang minta tarifnya sendiri — bentuk snapshotnya di
 * nota sudah menampung itu, jadi yang berubah cuma sumber angkanya.
 */
class LoyaltyPoints
{
    /** Rupiah belanja per satu poin yang didapat. */
    private const RATE = 10_000;

    /** Rupiah potongan per satu poin yang ditukar. */
    private const REDEEM_RATE = 1_000;

    /**
     * Tukar paling sedikit segini.
     *
     * Bukan untuk menyulitkan: penukaran receh membuat tiap nota punya baris
     * potongan seharga seribu rupiah, dan riwayat poin pasien jadi daftar
     * panjang yang tidak menceritakan apa pun.
     */
    public const MIN_REDEEM = 10;

    /**
     * Poin dari satu nota. Sisa di bawah tarif dibuang, bukan dibulatkan —
     * Rp19.000 tetap 1 poin, bukan 2, karena belum genap Rp20.000.
     */
    public static function earn(float $amount): int
    {
        return (int) floor(max(0.0, $amount) / self::RATE);
    }

    /** Nilai rupiah dari sejumlah poin yang ditukar. */
    public static function redeemValue(int $points): float
    {
        return (float) (max(0, $points) * self::REDEEM_RATE);
    }

    /**
     * Pangkas penukaran supaya tidak melebihi tagihannya.
     *
     * Kelebihannya sengaja dipangkas diam-diam, bukan ditolak: poin memotong
     * yang harus dibayar, ia bukan uang yang bisa diambil pulang sebagai
     * kembalian. Kasir yang menawarkan seluruh saldo pada tagihan kecil
     * bermaksud "pakai sebisanya", dan sisanya tetap tersimpan.
     *
     * Saldo yang tidak mencukupi adalah perkara lain — itu ditolak terang di
     * RedeemLoyaltyPointsAction, karena angkanya sudah terlanjur disebut ke
     * pasien.
     */
    public static function capToBill(int $points, float $payable): int
    {
        return (int) min(max(0, $points), floor(max(0.0, $payable) / self::REDEEM_RATE));
    }
}
