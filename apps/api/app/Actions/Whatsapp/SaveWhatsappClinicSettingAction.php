<?php

namespace App\Actions\Whatsapp;

use App\Actions\LogAuditAction;
use App\Models\Tenant;
use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\Auth;

/**
 * Simpan setelan gateway satu klinik — dipakai dashboard central maupun
 * pengaturan WhatsApp sisi klinik.
 *
 * API key kosong berarti kredensial lama dipertahankan; kata sandi gateway
 * ikut dikosongkan begitu API key diganti karena tidak dipakai lagi. Nilai
 * rahasia tidak pernah masuk properties audit, hanya faktanya berubah.
 */
class SaveWhatsappClinicSettingAction
{
    /**
     * @param  array{api_key?: ?string, session?: ?string, daily_message_limit?: ?int}  $data
     */
    public function handle(Tenant $tenant, array $data): WhatsappSetting
    {
        $setting = WhatsappSetting::withoutGlobalScopes()
            ->firstOrNew(['tenant_id' => $tenant->id]);

        $attributes = [];
        $changed = [];
        $labels = [];

        if (filled($data['api_key'] ?? null)) {
            $attributes['gateway_api_key'] = $data['api_key'];
            $attributes['gateway_password'] = null;
            $changed[] = 'gateway_api_key';
            $labels[] = 'API key diganti';
        }

        if (array_key_exists('session', $data) && $setting->session !== $data['session']) {
            $attributes['session'] = $data['session'];
            $changed[] = 'session';
            $labels[] = 'sesi diubah';
        }

        // Kolomnya unsigned integer; perbandingan dilakukan sebagai angka
        // supaya nilai dari basis data dan dari formulir tidak dianggap beda
        // hanya karena tipe.
        if (
            array_key_exists('daily_message_limit', $data)
            && (int) $setting->daily_message_limit !== (int) $data['daily_message_limit']
        ) {
            $attributes['daily_message_limit'] = $data['daily_message_limit'];
            $changed[] = 'daily_message_limit';
            $labels[] = 'batas harian diubah';
        }

        if ($attributes === []) {
            return $setting;
        }

        $setting->forceFill($attributes)->save();

        app(LogAuditAction::class)->handle(
            'whatsapp.clinic_setting_updated',
            $setting,
            Auth::user(),
            ['changed' => $changed],
            'Memperbarui setelan gateway WhatsApp klinik '.$tenant->name.' — '.implode(' / ', $labels).'.',
            $tenant,
        );

        return $setting;
    }
}
