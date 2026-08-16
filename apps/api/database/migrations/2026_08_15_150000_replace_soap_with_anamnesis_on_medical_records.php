<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ganti kerangka SOAP dengan format catatan yang dipakai klinik: anamnesa,
 * riwayat pemakaian skincare, dan riwayat alergi.
 *
 * Isi SOAP yang sudah tertulis TIDAK dibuang. Catatan medis adalah keterangan
 * tentang tubuh seseorang; menghapusnya diam-diam karena kolomnya berganti
 * bukan keputusan yang boleh diambil sebuah migrasi. Semua isinya dipindahkan
 * ke anamnesa lengkap dengan penanda bagian asalnya, baru kolom lamanya
 * dilepas.
 */
return new class extends Migration
{
    private const SOAP = [
        'subjective' => 'Subjective',
        'objective' => 'Objective',
        'assessment' => 'Assessment',
        'plan' => 'Plan',
    ];

    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->text('anamnesis')->nullable()->after('author_id');
            $table->text('skincare_history')->nullable()->after('anamnesis');
            $table->text('allergy_history')->nullable()->after('skincare_history');
        });

        $this->carryOverExistingNotes();

        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropColumn(array_keys(self::SOAP));
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            foreach (array_keys(self::SOAP) as $column) {
                $table->text($column)->nullable();
            }
        });

        // Isi anamnesa tidak dipecah kembali ke empat kolom: penanda bagiannya
        // hanya penunjuk asal, bukan pembatas yang bisa diandalkan.
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropColumn(['anamnesis', 'skincare_history', 'allergy_history']);
        });
    }

    private function carryOverExistingNotes(): void
    {
        DB::table('medical_records')
            ->orderBy('id')
            ->chunkById(200, function ($records) {
                foreach ($records as $record) {
                    $parts = [];

                    foreach (self::SOAP as $column => $heading) {
                        $value = trim((string) ($record->{$column} ?? ''));

                        if ($value !== '') {
                            $parts[] = $heading.': '.$value;
                        }
                    }

                    if ($parts === []) {
                        continue;
                    }

                    DB::table('medical_records')
                        ->where('id', $record->id)
                        ->update(['anamnesis' => implode("\n\n", $parts)]);
                }
            });
    }
};
