<?php

namespace App\Support;

/**
 * Alamat yang bisa dijangkau dari internet.
 *
 * Gateway pihak ketiga (WhatsApp, pengambil poster broadcast) menolak alamat
 * lokal/internal demi keamanan. Pemeriksaan yang sama dipakai di sini supaya
 * penolakan muncul sebelum pekerjaan diantrekan, bukan setelah antrean gagal
 * satu per satu.
 */
final class PublicUrl
{
    /**
     * Skema http/https, host wajib ada, dan host tidak boleh localhost maupun
     * literal IP privat/reserved — termasuk IPv6 loopback, ULA, dan
     * link-local.
     */
    public static function isGatewayReachable(?string $url): bool
    {
        $parts = parse_url((string) $url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        // Host berupa nama domain lolos; hanya literal IP yang disaring.
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return true;
        }

        // Loopback, privat, link-local, dan rentang terlarang lain (0.0.0.0,
        // 127/8, 10/8, 172.16/12, 192.168/16, 169.254/16, ::1, fc00::/7,
        // fe80::/10) semuanya ditolak oleh dua flag ini.
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
