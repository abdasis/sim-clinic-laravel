<?php

namespace App\Support;

use App\Contracts\WhatsappClient;
use App\Enums\WhatsappDriver;
use App\Models\WhatsappSetting;

/**
 * Pilih klien WhatsApp dari setelan tenant. Driver `qr` memakai sidecar
 * internal (URL & token dari env server), `gateway` memakai kredensial
 * yang diisi tenant. Driver manual tidak punya klien — pengirimannya lewat
 * tautan wa.me di tangan admin.
 */
class WhatsappClientFactory
{
    public function forCurrentTenant(): ?WhatsappClient
    {
        $setting = WhatsappSetting::query()->first();

        if ($setting?->driver === WhatsappDriver::Gateway && $setting->isGatewayReady()) {
            return new HttpGatewayWhatsappClient($setting->api_url, $setting->api_token);
        }

        if ($setting?->driver === WhatsappDriver::Qr && self::sidecarConfigured()) {
            return new HttpGatewayWhatsappClient(
                rtrim((string) config('services.wa_sidecar.url'), '/').'/send',
                (string) config('services.wa_sidecar.token'),
            );
        }

        return null;
    }

    public static function sidecarConfigured(): bool
    {
        return filled(config('services.wa_sidecar.url')) && filled(config('services.wa_sidecar.token'));
    }
}
