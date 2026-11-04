<?php

namespace App\Actions\Whatsapp;

use App\Actions\LogAuditAction;
use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\Auth;

/**
 * Lepaskan tautan sesi gateway dari pengaturan klinik.
 *
 * Nama sesi (slug) sengaja dipertahankan: setelah QR dipindai ulang, sesi
 * baru memakai nama yang sama dan tidak ada yang perlu mengisi ulang.
 */
class ClearGatewaySessionAction
{
    public function handle(WhatsappSetting $setting): WhatsappSetting
    {
        $hadSession = filled($setting->gateway_session_id);

        // Id sesi tersimpan terenkripsi; jangan pernah masukkan nilainya ke log.
        $setting->forceFill(['gateway_session_id' => null])->save();

        app(LogAuditAction::class)->handle(
            'whatsapp.gateway_session_cleared',
            $setting,
            Auth::user(),
            [
                'old' => ['gateway_session_id_set' => $hadSession],
                'new' => ['gateway_session_id' => null],
            ],
            'Menghapus sesi gateway WhatsApp "'.$setting->session.'". Nama sesinya tetap disimpan untuk disambungkan ulang.',
        );

        return $setting;
    }
}
