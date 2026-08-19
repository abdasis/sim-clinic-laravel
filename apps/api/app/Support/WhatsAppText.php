<?php

namespace App\Support;

/**
 * Rapikan teks jawaban AI ke format yang benar-benar dirender WhatsApp.
 *
 * Model sudah disuruh memakai format WhatsApp lewat system prompt, tapi tetap
 * sering tergelincir ke Markdown — dan WhatsApp tidak merender Markdown, jadi
 * yang sampai ke pasien adalah tanda bintangnya sendiri: "**Serum**" muncul apa
 * adanya. Jaring pengaman ini deterministik, tidak bergantung pada kepatuhan
 * model.
 *
 * Dipasang di jalur chatbot saja, bukan di WahaClient: broadcast dan pengingat
 * memakai template yang ditulis manusia dan sudah benar formatnya.
 *
 * ponytail: hanya menangani format satu tingkat. Format bersarang rumit dan
 * kutipan `>` (yang sebenarnya dikenal WhatsApp) dibiarkan lewat apa adanya —
 * tambahkan saat system prompt mulai menyuruh model memakainya.
 */
class WhatsAppText
{
    public static function normalize(string $text): string
    {
        // Urutannya penting: tebal diproses sebelum daftar, supaya baris yang
        // diawali "**Serum**" tidak keburu dibaca sebagai penanda daftar.
        $replacements = [
            // Judul Markdown jadi tebal; WhatsApp tidak punya heading.
            '/^#{1,6}[\t ]+(.+)$/m' => '*$1*',
            // Spasi di dalam penanda ikut dibuang: WhatsApp hanya menebalkan
            // bila bintangnya menempel langsung pada teksnya.
            '/\*\*[\t ]*(.+?)[\t ]*\*\*/' => '*$1*',
            '/__[\t ]*(.+?)[\t ]*__/' => '*$1*',
            '/~~[\t ]*(.+?)[\t ]*~~/' => '~$1~',
            // WhatsApp tidak mengenal daftar; bulatannya ditulis sendiri.
            '/^[\t ]*[*\-+][\t ]+/m' => '• ',
            '/^[\t ]*-{3,}[\t ]*$/m' => '',
            // Sisa spasi di ujung baris dan baris kosong beruntun.
            '/[\t ]+$/m' => '',
            '/\n{3,}/' => "\n\n",
        ];

        return trim(preg_replace(array_keys($replacements), array_values($replacements), $text) ?? $text);
    }
}
