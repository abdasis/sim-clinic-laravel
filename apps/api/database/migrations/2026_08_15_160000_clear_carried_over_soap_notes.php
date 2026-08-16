<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bersihkan catatan SOAP lama yang sempat dipindahkan ke kolom anamnesa.
 *
 * Migrasi sebelumnya memindahkannya karena isi rekam medis tidak boleh hilang
 * begitu saja saat kolomnya berganti. Setelah dikonfirmasi bahwa catatan itu
 * hanya data percobaan, sisanya dibersihkan.
 *
 * Hanya teks hasil pemindahan yang dihapus — yang dikenali dari penanda bagian
 * di awal barisnya. Catatan yang sejak awal ditulis dokter dalam format baru
 * tidak tersentuh.
 */
return new class extends Migration
{
    private const CARRIED_OVER_PREFIXES = ['Subjective: ', 'Objective: ', 'Assessment: ', 'Plan: '];

    public function up(): void
    {
        DB::table('medical_records')
            ->whereNotNull('anamnesis')
            ->orderBy('id')
            ->chunkById(200, function ($records) {
                foreach ($records as $record) {
                    if (! $this->isCarriedOver((string) $record->anamnesis)) {
                        continue;
                    }

                    DB::table('medical_records')
                        ->where('id', $record->id)
                        ->update(['anamnesis' => null]);
                }
            });
    }

    public function down(): void
    {
        // Tidak bisa dibatalkan: catatan yang dihapus tidak punya salinan.
    }

    private function isCarriedOver(string $anamnesis): bool
    {
        foreach (self::CARRIED_OVER_PREFIXES as $prefix) {
            if (str_starts_with($anamnesis, $prefix)) {
                return true;
            }
        }

        return false;
    }
};
