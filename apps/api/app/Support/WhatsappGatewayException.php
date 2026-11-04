<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * Penolakan dari gateway WhatsApp internal.
 *
 * Gateway menjawab dengan envelope `{success, message, data}` saat berhasil
 * dan `{success:false, message, error:{code, details}}` saat gagal. Kode HTTP
 * saja tidak cukup: 422 dipakai untuk sesi terputus maupun payload cacat,
 * sedangkan 429 menuntut jeda yang lamanya hanya diberitahu lewat header
 * `Retry-After`. Ketiganya disimpan apa adanya agar pemanggil bisa memilih
 * jeda, menjeda kampanye, atau menghanguskan penerima.
 */
class WhatsappGatewayException extends RuntimeException
{
    /**
     * Kode galat dari gateway, mis. `SESSION_NOT_CONNECTED`.
     *
     * Tidak diberi tipe di sini: `Exception::$code` sudah dideklarasikan PHP
     * dan menimpanya dengan tipe lain membuat kelas gagal dimuat. Nilainya
     * tetap string, sesuai kontrak gateway.
     *
     * @var string
     */
    public $code;

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $code,
        public readonly int $status,
        public readonly ?int $retryAfter,
        public readonly array $details,
        string $message,
    ) {
        parent::__construct($message);

        $this->code = $code;
    }

    /** Terjemahkan balasan galat gateway menjadi exception. */
    public static function fromResponse(Response $response): self
    {
        $code = $response->json('error.code');

        return new self(
            is_string($code) && $code !== '' ? $code : 'HTTP_'.$response->status(),
            $response->status(),
            self::retryAfterFrom($response),
            self::detailsFrom($response),
            self::messageFrom($response),
        );
    }

    /** Kegagalan sebelum gateway menjawab: DNS, koneksi ditolak, timeout. */
    public static function network(Throwable $e): self
    {
        return new self(
            'NETWORK_ERROR',
            0,
            null,
            [],
            'Tidak bisa menghubungi gateway WhatsApp: '.$e->getMessage(),
        );
    }

    private static function retryAfterFrom(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        return is_numeric($header) ? max(0, (int) $header) : null;
    }

    /** @return array<string, mixed> */
    private static function detailsFrom(Response $response): array
    {
        $details = $response->json('error.details');

        return is_array($details) ? $details : [];
    }

    /**
     * Kalimat dari gateway lebih dulu; balasan non-JSON (halaman proxy,
     * gateway mati) dipotong seadanya supaya tetap ada petunjuk.
     */
    private static function messageFrom(Response $response): string
    {
        $message = $response->json('message');

        if (is_array($message)) {
            $message = implode('; ', array_map(fn ($line): string => (string) $line, $message));
        }

        $message = is_string($message) ? trim($message) : '';

        if ($message === '') {
            $message = trim(mb_substr($response->body(), 0, 300));
        }

        return $message === ''
            ? 'Gateway WhatsApp menolak permintaan (HTTP '.$response->status().').'
            : $message;
    }
}
