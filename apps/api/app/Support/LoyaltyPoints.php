<?php

namespace App\Support;

use App\Models\LoyaltySetting;

/**
 * Konversi belanja ke poin loyalitas, dan poin kembali ke rupiah.
 *
 * Tarifnya milik tiap klinik (lihat LoyaltySetting), jadi kelas ini membacanya
 * lebih dulu — bukan lagi konstanta yang cuma bisa berubah lewat rilis.
 * Hitungannya sendiri tetap murni dan ada di satu tempat, supaya sisi kasir
 * dan sisi nota tidak pernah memakai rumus yang berbeda.
 *
 * Mengubah tarif tidak menulis ulang riwayat: poin yang didapat dan nilai
 * rupiah yang ditukar sudah disnapshot di kolom transaksi begitu notanya
 * terbit, jadi nota lama tetap menyebut angka yang berlaku saat itu.
 */
class LoyaltyPoints
{
    /**
     * Poin dari satu nota. Sisa di bawah tarif dibuang, bukan dibulatkan —
     * dengan tarif Rp10.000, belanja Rp19.000 tetap 1 poin karena belum
     * genap Rp20.000.
     */
    public static function earn(float $amount): int
    {
        return (int) floor(max(0.0, $amount) / self::rates()['earn_rate']);
    }

    /** Nilai rupiah dari sejumlah poin yang ditukar. */
    public static function redeemValue(int $points): float
    {
        return max(0, $points) * self::rates()['redeem_rate'];
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
        return (int) min(
            max(0, $points),
            floor(max(0.0, $payable) / self::rates()['redeem_rate']),
        );
    }

    /** Tukar paling sedikit segini, menurut setelan klinik ini. */
    public static function minRedeem(): int
    {
        return self::rates()['min_redeem'];
    }

    /**
     * Tarif klinik yang sedang aktif.
     *
     * Sengaja dibaca ulang tiap dipanggil, bukan diingat: satu nota cuma
     * memanggilnya beberapa kali, sementara tarif yang terlanjur diingat
     * setelah kasir menyimpannya kembali menghitung dengan angka lama —
     * kesalahan yang jauh lebih mahal daripada kueri yang dihemat.
     *
     * @return array{earn_rate: float, redeem_rate: float, min_redeem: int}
     */
    private static function rates(): array
    {
        return LoyaltySetting::current();
    }
}
