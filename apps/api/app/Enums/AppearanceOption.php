<?php

namespace App\Enums;

/**
 * Nilai yang boleh dipakai preferensi tampilan. Ditaruh di satu tempat supaya
 * aturan validasi dan daftar pilihan di frontend tidak bisa berbeda diam-diam.
 */
final class AppearanceOption
{
    /** Teal adalah tampilan bawaan; sisanya mengikuti skala resmi shadcn. */
    public const ACCENTS = [
        'teal', 'neutral', 'gray', 'zinc', 'slate', 'stone',
        'blue', 'violet', 'orange', 'green', 'rose', 'red', 'yellow', 'gold',
    ];

    public const MODES = ['light', 'dark', 'auto'];

    public const SIDEBAR_VARIANTS = ['sidebar', 'floating', 'inset'];

    /** Dipakai saat pengguna belum pernah menyentuh preferensinya. */
    public const DEFAULTS = [
        'accent' => 'teal',
        'mode' => 'auto',
        'sidebar_variant' => 'inset',
    ];
}
