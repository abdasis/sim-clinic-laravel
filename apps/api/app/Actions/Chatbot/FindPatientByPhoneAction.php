<?php

namespace App\Actions\Chatbot;

use App\Models\Patient;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cocokkan nomor pengirim WhatsApp dengan pasien terdaftar.
 *
 * Nomor pasien disimpan apa adanya seperti diketik admin — "0812 3456 7890",
 * "+62812...", "62812..." semuanya sah — sedangkan yang datang dari gateway
 * selalu bentuk internasional. Jadi pencocokannya tidak bisa sekadar samadengan.
 *
 * Dua langkah: ejaan yang lazim dicoba lebih dulu lewat kolom terindeks, dan
 * hanya kalau meleset barulah sisanya disisir sambil dinormalkan. Nomor
 * berspasi atau bertanda hubung tidak akan pernah tertangkap langkah pertama,
 * dan langkah kedua itulah yang menjamin pasiennya tetap ketemu.
 *
 * ponytail: langkah kedua menyisir daftar pasien satu klinik per potongan
 * 500 baris. Cukup untuk satu pesan masuk; kalau daftarnya sudah puluhan ribu
 * dan langkah pertama sering meleset, simpan kolom nomor ternormalisasi saat
 * pasien disimpan dan cocokkan langsung di database.
 */
class FindPatientByPhoneAction
{
    private const CHUNK = 500;

    public function handle(string $phone): ?Patient
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        return $this->matchCommonSpellings($normalized) ?? $this->scanForMatch($normalized);
    }

    /** Ejaan tanpa pemisah — bentuk yang dipakai hampir semua orang. */
    private function matchCommonSpellings(string $normalized): ?Patient
    {
        $national = '0'.substr($normalized, 2);

        return Patient::query()
            ->whereIn('whatsapp', [$normalized, $national, '+'.$normalized])
            ->first();
    }

    /** Sisanya — nomor berspasi, bertanda hubung, atau berkurung. */
    private function scanForMatch(string $normalized): ?Patient
    {
        $found = null;

        Patient::query()
            ->select(['id', 'whatsapp'])
            ->chunkById(self::CHUNK, function (Collection $patients) use ($normalized, &$found): bool {
                foreach ($patients as $patient) {
                    if (PhoneNumber::normalize($patient->whatsapp) === $normalized) {
                        $found = $patient;

                        return false;
                    }
                }

                return true;
            });

        return $found === null ? null : Patient::query()->find($found->id);
    }
}
