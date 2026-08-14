<?php

namespace App\Support;

/**
 * Konten company profile disimpan sebagai peta bahasa (`{"id": …, "en": …}`)
 * dan dikirim apa adanya ke frontend (spec 010, api-contracts.md). Helper ini
 * dipakai saat backend sendiri perlu satu bahasa, mis. untuk meta.
 */
class LocaleText
{
    public const FALLBACK = 'id';

    /** Bahasa yang punya berkas terjemahan lengkap. */
    public const SUPPORTED = ['id', 'en'];

    /**
     * Pilih satu bahasa, jatuh ke bahasa Indonesia bila versi yang diminta
     * belum ditulis — lebih baik tampil berbahasa Indonesia daripada kosong.
     *
     * @param  array<string, mixed>|string|null  $value
     */
    public static function pick(array|string|null $value, string $locale): mixed
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        return $value[$locale] ?? $value[self::FALLBACK] ?? null;
    }
}
