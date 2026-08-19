<?php

namespace App\Actions\Chatbot;

use App\Models\CompanyProfileSetting;

/**
 * Identitas dan kontak klinik yang boleh disebut ke pasien.
 *
 * Alamatnya diambil dari company profile, bukan ditulis ulang di sini: itu
 * satu-satunya tempat klinik memperbaruinya, dan chatbot tidak boleh menyebut
 * alamat yang berbeda dari yang tertera di halaman publiknya sendiri.
 */
class GetClinicInfoAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $tenant = app('tenant');
        $profile = CompanyProfileSetting::query()->first();

        return [
            'name' => $tenant->name,
            'phone' => $tenant->phone,
            'address' => $profile?->address,
            // Jam operasional belum punya tempat tersimpan; dikembalikan null
            // supaya AI menyatakan belum tersedia, bukan menebak sendiri.
            'operating_hours' => null,
        ];
    }
}
