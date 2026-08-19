<?php

namespace App\Support;

/**
 * Ubah dokumen Tiptap jadi teks polos.
 *
 * Dipakai saat isi rich-text harus dibaca mesin, bukan dirender: AI yang
 * menerima JSON node mentah akan menghabiskan token untuk struktur dan tetap
 * berpeluang salah membacanya. Yang dikirim cukup kalimatnya.
 *
 * ponytail: struktur yang dipertahankan hanya pemisah baris dan penanda butir.
 * Tabel, kutipan, dan pemformatan dalam baris rata jadi teks biasa — cukup
 * untuk dibacakan, dan bisa diperluas kalau nanti ada isi yang butuh
 * strukturnya utuh.
 */
class RichTextPlain
{
    /** Node yang isinya berdiri sendiri sebagai satu paragraf. */
    private const BLOCK_NODES = ['paragraph', 'heading', 'blockquote'];

    /**
     * Kosong mengembalikan null, bukan string kosong: yang memanggil perlu
     * bisa membedakan "belum ditulis" dari "ditulis tapi kosong".
     *
     * @param  array<string, mixed>|null  $document
     */
    public static function extract(?array $document): ?string
    {
        if (blank($document)) {
            return null;
        }

        $text = trim(preg_replace('/\n{3,}/', "\n\n", self::walk($document)) ?? '');

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function walk(array $node): string
    {
        $type = $node['type'] ?? null;

        if ($type === 'text') {
            return (string) ($node['text'] ?? '');
        }

        if ($type === 'hardBreak') {
            return "\n";
        }

        $children = is_array($node['content'] ?? null) ? $node['content'] : [];
        $inner = '';

        foreach ($children as $child) {
            $inner .= is_array($child) ? self::walk($child) : '';
        }

        if ($type === 'listItem') {
            // Butirnya ditandai supaya daftar tetap terbaca sebagai daftar
            // waktu dibacakan ulang oleh AI.
            return '- '.trim($inner)."\n";
        }

        if (in_array($type, self::BLOCK_NODES, true)) {
            return trim($inner)."\n\n";
        }

        return $inner;
    }
}
