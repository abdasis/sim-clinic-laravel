<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien WhatsApp Cloud API (Meta) untuk mengirim teks.
 *
 * Nomor pengirim (`phone_number_id`) milik tiap klinik, sedangkan token akses
 * milik aplikasi Meta platform. Token tidak pernah dicatat ke log atau
 * disimpan di basis data.
 */
class CloudApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiVersion,
        private readonly string $accessToken,
        private readonly string $phoneNumberId,
    ) {}

    /**
     * Kirim satu pesan teks. Mengembalikan id pesan dari Meta bila ada.
     *
     * Dipanggil hanya dari jalur yang sudah memeriksa jendela 24 jam; Meta
     * menolak balasan bebas di luar jendela itu.
     */
    public function sendText(string $phone, string $message): ?string
    {
        if ($this->accessToken === '' || $this->phoneNumberId === '') {
            throw new CloudApiException(0, null, 'Cloud API Meta belum dikonfigurasi.');
        }

        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->timeout(20)
            ->connectTimeout(10)
            ->retry(2, 300, throw: false)
            ->post($this->url(), [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ]);

        if (! $response->successful()) {
            $exception = CloudApiException::rejected('Cloud API Meta menolak pengiriman', $response);

            // Konteks sengaja tanpa token: log yang bocor tidak bisa ditarik
            // kembali dari penyimpanan.
            Log::error('Cloud API Meta menolak pengiriman pesan.', [
                'phone_number_id' => $this->phoneNumberId,
                'status' => $response->status(),
                'reason' => $exception->reason,
            ]);

            throw $exception;
        }

        $id = data_get($response->json(), 'messages.0.id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function url(): string
    {
        return rtrim($this->baseUrl, '/')
            .'/'.trim($this->apiVersion, '/')
            .'/'.$this->phoneNumberId
            .'/messages';
    }
}
