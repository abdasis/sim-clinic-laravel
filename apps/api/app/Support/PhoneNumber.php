<?php

namespace App\Support;

/**
 * Normalisasi nomor telepon Indonesia ke format internasional tanpa plus
 * (628xxxx) — bentuk yang diminta wa.me maupun gateway WhatsApp.
 */
class PhoneNumber
{
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '620')) {
            // Ketikan campuran "+62 081..." — buang nol ganda di tengah.
            $digits = '62'.substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        // Nomor seluler Indonesia valid: 62 + 9-13 digit.
        if (! str_starts_with($digits, '62') || strlen($digits) < 11 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }
}
