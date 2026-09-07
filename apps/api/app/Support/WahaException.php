<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
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

    /**
     * Kalimat yang dipakai gateway saat menahan laju kiriman.
     *
     * ponytail: mencocokkan teks memang rapuh — WAHA bisa mengubah
     * kalimatnya kapan saja, dan daftar ini tidak akan pernah lengkap. Yang
     * diandalkan lebih dulu tetap status 429; teksnya cuma jaring kedua,
     * karena engine WAHA tertentu membungkus penahanan laju WhatsApp sebagai
     * 4xx biasa. Begitu WAHA memberi kode atau header khusus untuk ini,
     * buang daftarnya dan baca kontraknya.
     */
    private const THROTTLE_HINTS = [
        'rate limit',
        'rate-limit',
        'ratelimit',
        'too many requests',
        'too many messages',
        'slow down',
        'temporarily blocked',
        'temporary ban',
        'flood',
    ];

    /**
     * Gateway sedang menahan laju kita, bukan menolak nomornya.
     *
     * Bedanya menentukan: penolakan nomor dihanguskan dan yang lain jalan
     * terus, sedangkan penahanan laju berarti seluruh blast harus berhenti
     * dulu. Terus menembak saat WhatsApp menahan laju adalah cara tercepat
     * membuat nomor klinik diblokir.
     */
    public function isThrottle(): bool
    {
        if ($this->status === 429) {
            return true;
        }

        return $this->reason !== null
            && Str::contains(Str::lower($this->reason), self::THROTTLE_HINTS);
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
