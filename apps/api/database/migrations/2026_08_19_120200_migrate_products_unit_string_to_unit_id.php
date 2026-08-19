<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pindahkan satuan yang sudah telanjur diketik ke tabel master.
 *
 * Nama dinormalkan (trim + huruf kecil) supaya "Botol", "botol ", dan "BOTOL"
 * berakhir sebagai satu baris — itulah seluruh alasan normalisasi ini
 * dikerjakan.
 *
 * Kolom string `unit` sengaja tidak dibuang di sini: selama ia masih ada,
 * migrasi ini bisa dibatalkan tanpa kehilangan satu nilai pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('products')
            ->select('tenant_id', 'unit')
            ->whereNotNull('unit')
            ->where('unit', '!=', '')
            ->distinct()
            ->get();

        $now = now();

        foreach ($rows as $row) {
            $name = mb_strtolower(trim((string) $row->unit));

            if ($name === '') {
                continue;
            }

            $unitId = DB::table('units')
                ->where('tenant_id', $row->tenant_id)
                ->where('name', $name)
                ->value('id');

            $unitId ??= DB::table('units')->insertGetId([
                'tenant_id' => $row->tenant_id,
                'name' => $name,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('products')
                ->where('tenant_id', $row->tenant_id)
                ->where('unit', $row->unit)
                ->update(['unit_id' => $unitId]);
        }
    }

    public function down(): void
    {
        // Nilai aslinya masih utuh di kolom string, jadi memutus tautannya
        // sudah cukup untuk kembali ke keadaan semula.
        DB::table('products')->update(['unit_id' => null]);
    }
};
