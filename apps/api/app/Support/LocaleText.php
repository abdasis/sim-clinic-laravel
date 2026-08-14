<?php

namespace App\Support;

/**
 * Konten company profile disimpan sebagai peta bahasa (`{"id": …, "en": …}`).
 * Helper ini memilih satu bahasa dan jatuh ke bahasa Indonesia bila versi
 * yang diminta belum ditulis — halaman lebih baik tampil berbahasa Indonesia
 * daripada kosong.
 */
class LocaleText
{
    public const FALLBACK = 'id';

    /**
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
