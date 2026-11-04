<?php

namespace App\Actions\Whatsapp;

use App\Actions\LogAuditAction;
use App\Models\Tenant;
use App\Models\WhatsappSetting;

/**
 * Pastikan klinik punya baris pengaturan WhatsApp sebelum sesinya disiapkan.
 *
 * Sebelumnya layar Koneksi menyuruh admin membuka menu Pengaturan yang sama
 * untuk membuat baris itu sendiri — dialog yang menyuruh membuka dirinya
 * sendiri. Nama sesi diisi dari slug klinik, sama seperti migrasi lama.
 */
class StoreGatewaySettingAction
{
    public function handle(Tenant $tenant): WhatsappSetting
    {
        $setting = WhatsappSetting::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['session' => $tenant->slug],
        );

        if ($setting->wasRecentlyCreated) {
            app(LogAuditAction::class)->handle(
                'whatsapp.setting_created',
                $setting,
                null,
                ['new' => ['session' => $setting->session]],
                'Menyiapkan pengaturan WhatsApp klinik dengan nama sesi "'.$setting->session.'".',
                $tenant,
            );
        }

        return $setting;
    }
}
