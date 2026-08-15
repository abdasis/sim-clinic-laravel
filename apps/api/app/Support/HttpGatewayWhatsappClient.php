<?php

namespace App\Support;

use App\Contracts\WhatsappClient;
use Illuminate\Support\Facades\Http;

/**
 * Klien gateway HTTP gaya Fonnte/Wablas — juga dipakai sidecar QR internal
 * karena kontrak endpoint-nya disamakan: POST form target+message, token di
 * header Authorization.
 */
class HttpGatewayWhatsappClient implements WhatsappClient
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $token,
    ) {}

    public function send(string $phone, string $message): void
    {
        $response = Http::timeout(20)
            ->withHeaders(['Authorization' => $this->token])
            ->asForm()
            ->post($this->apiUrl, ['target' => $phone, 'message' => $message]);

        if (! $response->successful()) {
            throw new \RuntimeException('Gateway WhatsApp menolak: HTTP '.$response->status());
        }
    }
}
