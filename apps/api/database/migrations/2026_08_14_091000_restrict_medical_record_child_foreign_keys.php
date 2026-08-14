<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tindakan dan foto klinis tidak boleh ikut terhapus bersama rekam
 * medisnya — keduanya bukti perawatan yang berdiri sendiri.
 *
 * ponytail: menyimpang dari pola cascade di workflow normalisasi, atas
 * pertimbangan integritas rekam klinis. Dilewati di SQLite.
 */
return new class extends Migration
{
    private const TABLES = ['treatment_records', 'medical_photos'];

    public function up(): void
    {
        $this->apply('restrictOnDelete');
    }

    public function down(): void
    {
        $this->apply('cascadeOnDelete');
    }

    private function apply(string $rule): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($rule): void {
                $blueprint->dropForeign(['medical_record_id']);
                $blueprint->foreign('medical_record_id')->references('id')->on('medical_records')->{$rule}();
            });
        }
    }
};
