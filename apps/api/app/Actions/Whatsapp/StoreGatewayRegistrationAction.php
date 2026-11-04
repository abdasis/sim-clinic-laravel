<?php

namespace App\Actions\Whatsapp;

use App\Actions\LogAuditAction;
use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\Auth;

/**
 * Simpan kredensial pendaftaran akun gateway klinik. Disimpan sebelum langkah
 * API key supaya percobaan ulang bisa login, bukan mendaftar tenant kedua.
 */
class StoreGatewayRegistrationAction
{
    public function handle(WhatsappSetting $setting, string $tenantKey, string $password): WhatsappSetting
    {
        $setting->forceFill([
            'gateway_tenant_key' => $tenantKey,
            'gateway_password' => $password,
        ])->save();

        // Nilai kredensial tidak masuk properties: log yang bocor tidak bisa
        // ditarik kembali dari penyimpanan.
        app(LogAuditAction::class)->handle(
            'whatsapp.gateway_registered',
            $setting,
            Auth::user(),
            ['changed' => ['gateway_tenant_key', 'gateway_password']],
            'Menyimpan kredensial pendaftaran akun gateway WhatsApp klinik.',
        );

        return $setting;
    }
}
