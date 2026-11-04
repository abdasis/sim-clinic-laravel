<?php

namespace App\Services;

use App\Actions\Whatsapp\StoreGatewayApiKeyAction;
use App\Actions\Whatsapp\StoreGatewayRegistrationAction;
use App\Actions\Whatsapp\StoreGatewayWebhookAction;
use App\Models\WhatsappSetting;
use App\Support\WhatsappGatewayClient;
use App\Support\WhatsappGatewayException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menyiapkan akun gateway WhatsApp untuk satu klinik.
 *
 * Klinik pertama kali mendaftar sebagai tenant gateway, lalu mendapat API key
 * yang dipakai semua panggilan sesi dan pesan, lalu webhook pesan masuk yang
 * dipakai kotak masuk dan chatbot. Proses ini bisa terputus di tengah jalan,
 * jadi `ensure()` mengulang dari langkah yang belum selesai alih-alih
 * mendaftar ulang.
 */
class WhatsappGatewayProvisioningService
{
    /** Peristiwa gateway yang dibutuhkan kotak masuk dan chatbot. */
    private const MESSAGE_EVENTS = [
        'message.received',
        'message.sent',
        'message.delivered',
        'message.read',
        'message.failed',
    ];

    public function __construct(
        private readonly WhatsappGatewayClient $client,
        private readonly StoreGatewayRegistrationAction $storeRegistration,
        private readonly StoreGatewayApiKeyAction $storeApiKey,
        private readonly StoreGatewayWebhookAction $storeWebhook,
    ) {}

    /**
     * Lengkapi kredensial gateway klinik. Aman dipanggil berulang: hanya
     * langkah yang belum selesai yang dijalankan, dan bila semuanya sudah
     * lengkap gateway tidak dihubungi sama sekali.
     */
    public function ensure(WhatsappSetting $setting): WhatsappSetting
    {
        if ($setting->provisioned()) {
            return $setting;
        }

        // Langkah dicatat sebelum dijalankan supaya kegagalan login tidak
        // tertukar dengan kegagalan pendaftaran.
        $step = blank($setting->gateway_tenant_key) ? 'register' : 'login';

        try {
            if (blank($setting->gateway_api_key)) {
                $accessToken = $this->accessToken($setting);

                $step = 'api-key';

                $this->storeApiKey->handle($setting, $this->client->createApiKey(
                    (string) $setting->gateway_tenant_key,
                    $accessToken,
                    $this->clinicName($setting),
                ));
            }

            $step = 'webhook';

            $this->ensureWebhook($setting);
        } catch (Throwable $e) {
            Log::error('Provisioning akun gateway WhatsApp klinik gagal.', [
                'exception' => $e,
                'tenant_id' => $setting->tenant_id,
                'step' => $step,
            ]);

            throw $e;
        }

        return $setting;
    }

    /**
     * Daftarkan webhook pesan masuk ke URL aplikasi. Token dibuat sekali dan
     * disimpan bersama rahasianya, jadi pengulangan tidak membuat webhook
     * ganda selama langkah ini berhasil.
     */
    private function ensureWebhook(WhatsappSetting $setting): void
    {
        $token = filled($setting->gateway_webhook_token)
            ? (string) $setting->gateway_webhook_token
            : Str::random(40);

        $webhook = $this->client->createWebhook(
            (string) $setting->gateway_tenant_key,
            (string) $setting->gateway_api_key,
            rtrim((string) config('app.url'), '/').'/api/whatsapp/webhook/'.$token,
            self::MESSAGE_EVENTS,
        );

        $this->storeWebhook->handle($setting, $token, $webhook['secret']);
    }

    /**
     * Access token untuk membuat API key. Klinik yang belum terdaftar
     * didaftarkan lebih dulu; yang sudah terdaftar masuk dengan kata sandi
     * yang tersimpan dari percobaan sebelumnya.
     */
    private function accessToken(WhatsappSetting $setting): string
    {
        if (blank($setting->gateway_tenant_key)) {
            // ponytail: email pendaftaran dikunci ke id klinik. Bila register
            // sudah diterima gateway tetapi proses mati sebelum kredensial
            // tersimpan, percobaan ulang akan ditolak 409 secara permanen.
            // Jalur pemulihannya administratif: klaim/ganti email tenant di
            // gateway, lalu isi kredensialnya lewat pengaturan.
            $password = Str::password(24);

            $auth = $this->client->register(
                $this->clinicName($setting),
                $this->clinicName($setting),
                $this->email($setting),
                $password,
            );

            $this->storeRegistration->handle($setting, $auth['tenant_key'], $password);

            return $auth['access_token'];
        }

        $password = $setting->gateway_password;

        if (blank($password)) {
            throw new WhatsappGatewayException(
                'GATEWAY_PASSWORD_MISSING',
                0,
                null,
                [],
                'Kata sandi akun gateway klinik tidak tersimpan; provisioning tidak bisa dilanjutkan.',
            );
        }

        return $this->client->login($this->email($setting), $password)['access_token'];
    }

    /** Alamat pendaftaran tetap per klinik supaya tidak pernah ada dua akun. */
    private function email(WhatsappSetting $setting): string
    {
        return 'tenant-'.$setting->tenant_id.'@'.config('whatsapp_gateway.account_domain');
    }

    private function clinicName(WhatsappSetting $setting): string
    {
        return $setting->tenant?->name ?: 'Klinik '.$setting->tenant_id;
    }
}
