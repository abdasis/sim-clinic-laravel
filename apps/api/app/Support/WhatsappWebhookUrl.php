<?php

namespace App\Support;

/**
 * URL webhook WhatsApp milik aplikasi ini, mengikuti `app.url` supaya dev
 * dan produksi tidak perlu mengisi alamat terpisah.
 *
 * Token adalah bagian URL — gateway memanggilnya langsung — jadi aman
 * ditampilkan ke admin. Rahasianya (secret) tetap tidak pernah keluar.
 */
final class WhatsappWebhookUrl
{
    public static function for(?string $token): ?string
    {
        if (blank($token)) {
            return null;
        }

        return rtrim((string) config('app.url'), '/').'/api/whatsapp/webhook/'.$token;
    }
}
