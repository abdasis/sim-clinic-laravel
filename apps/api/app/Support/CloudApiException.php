<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Penolakan dari WhatsApp Cloud API (Meta), berikut alasan yang disebutkan.
 *
 * Bentuk galat Meta adalah `{"error": {"message": "...", "code": 190}}`, jadi
 * yang dibaca lebih dulu `error.message`. Tanpa kalimat aslinya, layar admin
 * hanya melihat "HTTP 400" dan sebabnya harus ditebak.
 */
class CloudApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly ?string $reason,
        string $message,
        public readonly ?int $errorCode = null,
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
            self::codeFrom($response),
        );
    }

    /**
     * Meta sedang menahan laju kiriman, bukan menolak nomornya.
     *
     * 429 berbagi kelas 4xx dengan penolakan nomor, jadi kode galatnya yang
     * memisahkan: tanpa ini penerima dihanguskan permanen padahal yang perlu
     * justru berhenti menembak sebentar.
     */
    public function isThrottle(): bool
    {
        return $this->status === 429
            || in_array($this->errorCode, [130429, 131056, 80007], true);
    }

    private static function reasonFrom(Response $response): ?string
    {
        $message = $response->json('error.message') ?? $response->json('error');

        if (is_array($message)) {
            $message = implode('; ', array_map(fn ($line) => (string) $line, $message));
        }

        $reason = is_string($message) ? trim($message) : '';

        if ($reason === '') {
            $reason = trim(mb_substr($response->body(), 0, 300));
        }

        return $reason === '' ? null : mb_substr($reason, 0, 300);
    }

    private static function codeFrom(Response $response): ?int
    {
        $code = $response->json('error.code');

        return is_numeric($code) ? (int) $code : null;
    }
}
