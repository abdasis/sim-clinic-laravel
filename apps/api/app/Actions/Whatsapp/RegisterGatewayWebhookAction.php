<?php

namespace App\Actions\Whatsapp;

use App\Models\WhatsappSetting;
use App\Support\PublicUrl;
use App\Support\WhatsappGatewayClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Daftarkan webhook pesan masuk untuk klinik yang memakai API key manual.
 *
 * Klinik mode manual tidak punya tenant key, jadi pendaftaran ini hanya
 * membawa API key. Alamat webhook diambil dari APP_URL: tanpa domain publik
 * gateway tidak akan pernah bisa memanggilnya, dan memanggil gateway untuk
 * alamat yang pasti ditolak hanya membuang waktu.
 */
class RegisterGatewayWebhookAction
{
    /** Peristiwa gateway yang dibutuhkan kotak masuk dan chatbot. */
    public const MESSAGE_EVENTS = [
        'message.received',
        'message.sent',
        'message.delivered',
        'message.read',
        'message.failed',
    ];

    public function __construct(
        private readonly WhatsappGatewayClient $client,
        private readonly StoreGatewayWebhookAction $storeWebhook,
    ) {}

    /**
     * @return bool true bila webhook baru benar-benar didaftarkan
     */
    public function handle(WhatsappSetting $setting): bool
    {
        if (blank($setting->gateway_api_key)) {
            return false;
        }

        // Sudah terdaftar: rahasia hanya dikembalikan sekali, jadi webhook
        // yang rahasianya tersimpan tidak boleh dibuat ulang.
        if (filled($setting->gateway_webhook_secret) && filled($setting->gateway_webhook_token)) {
            return false;
        }

        $baseUrl = rtrim((string) config('app.url'), '/');

        if (! PublicUrl::isGatewayReachable($baseUrl)) {
            return false;
        }

        $token = filled($setting->gateway_webhook_token)
            ? (string) $setting->gateway_webhook_token
            : Str::random(40);

        try {
            $webhook = $this->client->createWebhook(
                null,
                (string) $setting->gateway_api_key,
                $baseUrl.'/api/whatsapp/webhook/'.$token,
                self::MESSAGE_EVENTS,
            );
        } catch (Throwable $e) {
            Log::error('Pendaftaran webhook gateway WhatsApp klinik gagal.', [
                'exception' => $e,
                'tenant_id' => $setting->tenant_id,
            ]);

            throw $e;
        }

        $this->storeWebhook->handle($setting, $token, $webhook['secret']);

        return true;
    }
}
