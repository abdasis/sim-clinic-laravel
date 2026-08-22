<?php

namespace App\Actions\Chatbot;

use App\Models\CompanyProfileSetting;
use App\Support\ClinicIdentity;

/**
 * Identitas, kontak, dan jam buka klinik yang boleh disebut ke pasien.
 *
 * Semuanya diambil dari company profile, bukan ditulis ulang di sini: itu
 * satu-satunya tempat klinik memperbaruinya, dan chatbot tidak boleh menyebut
 * alamat atau jam buka yang berbeda dari yang tertera di halaman publiknya
 * sendiri.
 */
class GetClinicInfoAction
{
    /** Kunci hari disimpan dalam bahasa Inggris; yang dibaca AI bahasa kita. */
    private const DAY_LABELS = [
        'monday' => 'Senin',
        'tuesday' => 'Selasa',
        'wednesday' => 'Rabu',
        'thursday' => 'Kamis',
        'friday' => 'Jumat',
        'saturday' => 'Sabtu',
        'sunday' => 'Minggu',
    ];

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $tenant = app('tenant');
        $profile = CompanyProfileSetting::query()->first();

        return [
            'name' => ClinicIdentity::displayName($tenant),
            'phone' => $tenant->phone,
            'address' => $profile?->address,
            // Alamat tertulis saja menyisakan pekerjaan di sisi pasien: ia
            // harus mengetik ulang ke aplikasi peta, dan nama komplek yang
            // mirip membuatnya berakhir di jalan yang salah.
            'maps_url' => $profile?->maps_url,
            'operating_hours' => $this->formatHours($profile?->operating_hours),
        ];
    }

    /**
     * Susun ulang jadi daftar tujuh hari berurutan.
     *
     * Urutannya dipaksa Senin sampai Minggu, tidak mengikuti urutan kunci yang
     * kebetulan tersimpan — AI membacakannya apa adanya, dan daftar hari yang
     * meloncat-loncat terbaca aneh oleh pasien.
     *
     * Belum diatur mengembalikan daftar kosong, bukan tujuh baris tebakan:
     * yang belum diketahui harus terbaca sebagai belum diketahui.
     *
     * @param  array<string, mixed>|null  $hours
     * @return array<int, array<string, mixed>>
     */
    private function formatHours(?array $hours): array
    {
        if (blank($hours)) {
            return [];
        }

        $formatted = [];

        foreach (self::DAY_LABELS as $key => $label) {
            $day = $hours[$key] ?? null;
            $isOpen = is_array($day) && ($day['is_open'] ?? false);

            $formatted[] = [
                'day' => $label,
                'is_open' => (bool) $isOpen,
                'open' => $isOpen ? ($day['open'] ?? null) : null,
                'close' => $isOpen ? ($day['close'] ?? null) : null,
            ];
        }

        return $formatted;
    }
}
