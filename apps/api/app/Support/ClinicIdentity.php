<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Nama klinik sebagaimana ia menyebut dirinya sendiri ke pasien.
 *
 * `tenants.name` adalah nama pendaftaran — diketik sekali saat klinik dibuat,
 * kerap masih berupa singkatan atau nama sementara. Yang dipelihara klinik
 * adalah `site_name` di pengaturan profil, dan itulah yang tertera di
 * sidebar, kop nota, serta halaman publiknya.
 *
 * Sebelum ini tiap saluran memilih sendiri: sidebar memakai site_name,
 * sementara sapaan chatbot, pengingat WhatsApp, dan variabel {klinik} di
 * broadcast memakai nama pendaftaran. Pasien yang sama bisa menerima dua nama
 * berbeda dari klinik yang sama dalam satu hari.
 */
class ClinicIdentity
{
    /**
     * Nama yang boleh disebut ke pasien. Jatuh ke nama pendaftaran bila
     * kliniknya belum pernah mengisi profil — lebih baik nama seadanya
     * daripada sapaan tanpa nama sama sekali.
     */
    public static function displayName(?Tenant $tenant = null): string
    {
        $tenant ??= app()->bound('tenant') ? app('tenant') : null;

        if ($tenant === null) {
            return '';
        }

        $siteName = LocaleText::pick(
            $tenant->loadMissing('companyProfile')->companyProfile?->site_name,
            app()->getLocale(),
        );

        return is_string($siteName) && trim($siteName) !== ''
            ? $siteName
            : (string) $tenant->name;
    }
}
