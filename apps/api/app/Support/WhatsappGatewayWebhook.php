<?php

namespace App\Support;

/**
 * Verifikasi tanda tangan webhook gateway WhatsApp.
 *
 * Tanda tangan menutup `timestamp + "." + isi mentah permintaan` dengan
 * HMAC-SHA256 memakai rahasia klinik. Isi mentah dipakai apa adanya — bukan
 * hasil parse ulang — karena satu spasi berbeda saja membuat hash-nya tidak
 * cocok.
 */
class WhatsappGatewayWebhook
{
    /** Selisih waktu maksimum yang masih diterima, dalam detik. */
    public const TOLERANCE_SECONDS = 300;

    /**
     * Tanda tangan sah hanya bila bentuknya benar, waktunya masih segar, dan
     * hash-nya cocok. Timestamp bukan angka diperlakukan sebagai tidak sah,
     * bukan sebagai nol.
     */
    public static function signatureIsValid(
        string $secret,
        string $timestamp,
        string $rawBody,
        ?string $signature,
    ): bool {
        if ($secret === '' || $signature === null || ! ctype_digit($timestamp)) {
            return false;
        }

        // Kiriman yang terlalu tua boleh jadi pemutaran ulang; yang bertanggal
        // jauh di masa depan menandakan jam pengirim yang salah. Keduanya
        // ditolak.
        if (abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expected, substr($signature, 7));
    }
}
