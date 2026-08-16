<?php

namespace App\Support;

use App\Models\Patient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Jejak yang ditinggalkan seorang pasien di data klinik.
 *
 * Dipakai untuk memutuskan boleh-tidaknya sebuah data pasien dihapus
 * permanen. Pasien yang sudah pernah datang membawa booking, rekam medis, dan
 * nota — menghapusnya berarti membuang catatan medis dan riwayat keuangan
 * yang harus tetap ada. Yang boleh benar-benar hilang hanya pendaftaran yang
 * salah ketik dan belum sempat dipakai.
 */
class PatientReferences
{
    /**
     * @var array<int, array{table: string, column: string}>
     */
    private const SOURCES = [
        ['table' => 'bookings', 'column' => 'patient_id'],
        ['table' => 'medical_records', 'column' => 'patient_id'],
        ['table' => 'transactions', 'column' => 'patient_id'],
        ['table' => 'broadcast_recipients', 'column' => 'patient_id'],
    ];

    /** @var array<int, true> */
    private array $referenced = [];

    /** @var array<int, true> Pasien yang jejaknya sudah pernah ditarik. */
    private array $loaded = [];

    /**
     * Kumpulkan jejak seluruh baris satu halaman sekaligus, bukan satu kueri
     * per pasien.
     *
     * @param  Collection<int, Patient>|array<int, int>  $patients
     */
    public function preload(Collection|array $patients): void
    {
        $ids = $patients instanceof Collection
            ? $patients->pluck('id')->all()
            : $patients;

        if ($ids === []) {
            return;
        }

        foreach ($ids as $id) {
            $this->loaded[(int) $id] = true;
        }

        foreach (self::SOURCES as $source) {
            DB::table($source['table'])
                ->whereIn($source['column'], $ids)
                ->distinct()
                ->pluck($source['column'])
                ->each(function ($id): void {
                    $this->referenced[(int) $id] = true;
                });
        }
    }

    public function has(int $patientId): bool
    {
        if (! isset($this->loaded[$patientId])) {
            $this->preload([$patientId]);
        }

        return isset($this->referenced[$patientId]);
    }
}
