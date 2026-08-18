<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien WAHA (WhatsApp HTTP API) — satu-satunya penyedia WhatsApp.
 *
 * Alamat dan API key-nya milik pengelola platform (satu server untuk semua
 * klinik); nama sesinya milik tiap klinik. Ketiganya diserahkan saat
 * pembuatan, jadi klien ini tidak perlu tahu soal tenant maupun database.
 *
 * Hanya arah keluar: kirim pesan dan urus sesinya. Tidak ada webhook masuk.
 */
class WahaClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $session,
    ) {}

    /**
     * Kirim satu pesan. Melempar bila gagal — pemanggil yang memutuskan
     * pengulangan dan pencatatan statusnya.
     */
    public function send(string $phone, string $message): void
    {
        $chatId = PhoneNumber::normalize($phone);

        if ($chatId === null) {
            throw new RuntimeException('Nomor tujuan tidak valid: '.$phone);
        }

        $response = $this->request()->post($this->url('/api/sendText'), [
            'session' => $this->session,
            'chatId' => $chatId.'@c.us',
            'text' => $message,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('WAHA menolak pengiriman: HTTP '.$response->status());
        }
    }

    /** @return array<string, mixed> */
    public function sessionStatus(): array
    {
        return (array) $this->request()
            ->get($this->url('/api/sessions/'.$this->session))
            ->throw()
            ->json();
    }

    /** Idempoten di sisi WAHA: sesi yang sudah jalan tidak dimulai dua kali. */
    public function startSession(): void
    {
        $this->request()
            ->post($this->url('/api/sessions/'.$this->session.'/start'))
            ->throw();
    }

    public function qrCode(): ?string
    {
        $qr = $this->request()
            ->get($this->url('/api/'.$this->session.'/auth/qr'))
            ->json('qr');

        return is_string($qr) ? $qr : null;
    }

    public function logout(): void
    {
        $this->request()
            ->post($this->url('/api/sessions/stop'), [
                'name' => $this->session,
                'logout' => true,
            ])
            ->throw();
    }

    private function request(): PendingRequest
    {
        return Http::timeout(20)->withHeaders(['X-Api-Key' => $this->apiKey]);
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}
