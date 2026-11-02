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
}
