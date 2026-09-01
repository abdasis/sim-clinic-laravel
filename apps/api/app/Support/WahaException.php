<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Penolakan dari gateway WhatsApp, berikut alasan yang gateway sebutkan.
 *
 * Kode statusnya saja tidak cukup: WAHA memakai 422 yang sama untuk sesi
 * yang terputus, nomor yang ditolak, dan payload yang cacat. Tanpa kalimat
 * aslinya yang tersisa di layar admin cuma "HTTP 422", dan sebabnya cuma
 * bisa ditebak — persis yang terjadi pada blast 130 pasien di isu #320,
 * yang didiagnosis sebagai rate limit tanpa satu pun bukti dari gateway.
 */
class WahaException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly ?string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function rejected(string $what, Response $response): self
    {
        $reason = self::reasonFrom($response);

        return new self(
            $response->status(),
            $reason,
            $what.': HTTP '.$response->status().($reason === null ? '' : ' — '.$reason),
        );
    }

    /**
     * Kalimat error milik gateway, apa pun bentuk balasannya.
     *
     * WAHA membalas {"message": "..."} atau, untuk kesalahan validasi,
     * {"message": ["...", "..."]}. Balasan yang bukan JSON sama sekali
     * (halaman error proxy, gateway mati) tetap dipotong seadanya — sepotong
     * teks asing masih lebih menuntun daripada tidak ada apa-apa.
     */
    private static function reasonFrom(Response $response): ?string
    {
        $message = $response->json('message') ?? $response->json('error');

        if (is_array($message)) {
            $message = implode('; ', array_map(fn ($line) => (string) $line, $message));
        }

        $reason = is_string($message) ? trim($message) : '';

        if ($reason === '') {
            $reason = trim(mb_substr($response->body(), 0, 200));
        }

        return $reason === '' ? null : $reason;
    }
}
