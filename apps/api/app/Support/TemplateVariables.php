<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Pemetaan key variabel template Cloud API ke nilai penerima.
 *
 * Template resmi Meta memakai placeholder bernomor ({{1}}, {{2}}), sedangkan
 * aplikasi ini menyimpan nama variabel yang bisa dibaca manusia. Satu
 * resolver dipakai bersama oleh pembuatan broadcast, mesin pengingat, dan
 * pesan uji supaya urutan nilainya tidak pernah berbeda antar jalur.
 */
class TemplateVariables
{
    /** @var array<int, string> */
    public const KEYS = [
        'nama',
        'klinik',
        'klinik_telepon',
        'layanan_terakhir',
        'tanggal_terakhir',
        'hari_sejak_kunjungan',
    ];

    /** @var array<int, string> */
    public const HEADER_TYPES = ['TEXT', 'IMAGE', 'DOCUMENT', 'VIDEO'];

    /**
     * Nilai sesuai urutan params, siap dikirim sebagai body parameters.
     *
     * @param  array<int, string>  $params
     * @param  array<string, string>  $variables
     * @return array<int, string>
     */
    public static function resolve(array $params, array $variables): array
    {
        $full = $variables + [
            'klinik' => ClinicIdentity::displayName(),
            'klinik_telepon' => (string) (app()->bound('tenant') ? app('tenant')->phone : ''),
        ];

        return array_map(
            fn (string $key): string => (string) ($full[$key] ?? ''),
            array_values($params),
        );
    }

    /**
     * Snapshot pesan dari body template: {{1}}, {{2}} diganti nilainya.
     *
     * @param  array<int, string>  $values
     */
    public static function render(string $body, array $values): string
    {
        $rendered = preg_replace_callback('/\{\{\s*(\d+)\s*\}\}/', function (array $match) use ($values): string {
            $index = (int) $match[1] - 1;

            return $values[$index] ?? $match[0];
        }, $body);

        return $rendered ?? $body;
    }

    /**
     * Render body template lokal klinik: placeholder bernama `{nama}` maupun
     * kurung ganda `{{nama}}`, termasuk alias lama seperti `{{nama_pasien}}`.
     * Placeholder bernomor `{{1}}` tetap dikenali mengikuti urutan KEYS.
     *
     * @param  array<string, string>  $variables
     */
    public static function renderNamed(string $body, array $variables): string
    {
        $full = $variables + [
            'klinik' => ClinicIdentity::displayName(),
            'klinik_telepon' => (string) (app()->bound('tenant') ? app('tenant')->phone : ''),
        ];

        $numbered = array_map(
            fn (string $key): string => (string) ($full[$key] ?? ''),
            self::KEYS,
        );

        $replacements = [];

        foreach ($full as $key => $value) {
            $replacements['{'.$key.'}'] = (string) $value;
            $replacements['{{'.$key.'}}'] = (string) $value;
        }

        $aliases = [
            'nama_pasien' => 'nama',
            'nama_treatment' => 'layanan_terakhir',
            'tanggal_treatment' => 'tanggal_terakhir',
            'nomor_clinic' => 'klinik_telepon',
        ];

        foreach ($aliases as $alias => $key) {
            if (isset($full[$key])) {
                $replacements['{'.$alias.'}'] = (string) $full[$key];
                $replacements['{{'.$alias.'}}'] = (string) $full[$key];
            }
        }

        return strtr(self::render($body, $numbered), $replacements);
    }

    /** Jumlah placeholder bernomor unik di dalam body. */
    public static function placeholderCount(string $body): int
    {
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $matches);

        return count(array_unique($matches[1]));
    }

    /**
     * Nilai contoh untuk pesan uji — klinik dan nomornya tetap yang asli,
     * sisanya karangan supaya admin bisa melihat bentuk pesannya.
     *
     * @return array<string, string>
     */
    public static function examples(): array
    {
        return [
            'nama' => 'Budi',
            'layanan_terakhir' => 'Facial Glow',
            'tanggal_terakhir' => Carbon::now()->translatedFormat('d F Y'),
            'hari_sejak_kunjungan' => '30',
        ];
    }
}
