<?php

namespace App\Actions\Whatsapp;

use App\Actions\LogAuditAction;
use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\Auth;

/**
 * Simpan id sesi gateway yang dipakai klinik ini. Id berasal dari gateway:
 * hasil sesi yang baru dibuat, atau sesi lama yang ditemukan kembali lewat
 * slug — bukan tebakan aplikasi.
 */
class StoreGatewaySessionAction
{
    public function handle(WhatsappSetting $setting, string $sessionId): WhatsappSetting
    {
        $setting->forceFill(['gateway_session_id' => $sessionId])->save();

        app(LogAuditAction::class)->handle(
            'whatsapp.gateway_session_linked',
            $setting,
            Auth::user(),
            ['changed' => ['gateway_session_id']],
            'Menautkan sesi gateway WhatsApp "'.$setting->session.'" ke pengaturan klinik.',
        );

        return $setting;
    }
}
