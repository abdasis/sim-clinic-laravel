<?php

namespace App\Actions\Whatsapp;

use App\Actions\LogAuditAction;
use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\Auth;

/**
 * Simpan API key gateway klinik sekaligus kosongkan kata sandi: setelah API
 * key ada, akun tidak perlu dimasuki lagi.
 */
class StoreGatewayApiKeyAction
{
    public function handle(WhatsappSetting $setting, string $apiKey): WhatsappSetting
    {
        $setting->forceFill([
            'gateway_api_key' => $apiKey,
            'gateway_password' => null,
        ])->save();

        // Nilai API key tidak dicatat, hanya fakta bahwa kredensial lengkap.
        app(LogAuditAction::class)->handle(
            'whatsapp.gateway_provisioned',
            $setting,
            Auth::user(),
            ['changed' => ['gateway_api_key']],
            'Akun gateway WhatsApp klinik lengkap; API key tersimpan dan kata sandi dikosongkan.',
        );

        return $setting;
    }
}
