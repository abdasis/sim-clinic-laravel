<?php

namespace App\Actions\Whatsapp;

use App\Actions\LogAuditAction;
use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\Auth;

/**
 * Simpan rahasia webhook gateway beserta token pencarinya. Rahasia hanya
 * dikembalikan gateway sekali saat webhook dibuat; klinik yang kehilangannya
 * harus membuat ulang webhook.
 */
class StoreGatewayWebhookAction
{
    public function handle(WhatsappSetting $setting, string $token, string $secret): WhatsappSetting
    {
        $setting->forceFill([
            'gateway_webhook_token' => $token,
            'gateway_webhook_secret' => $secret,
        ])->save();

        // Nilai rahasia tidak masuk properties: log yang bocor tidak bisa
        // ditarik kembali dari penyimpanan.
        app(LogAuditAction::class)->handle(
            'whatsapp.gateway_webhook_registered',
            $setting,
            Auth::user(),
            ['changed' => ['gateway_webhook_token', 'gateway_webhook_secret']],
            'Mendaftarkan webhook pesan masuk klinik di gateway WhatsApp; rahasia verifikasi tersimpan.',
        );

        return $setting;
    }
}
