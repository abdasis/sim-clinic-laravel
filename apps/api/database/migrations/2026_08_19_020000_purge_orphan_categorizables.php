<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Buang baris pivot kategori yang itemnya sudah tidak ada.
 *
 * Pivot kategori polimorfik, jadi tidak ada foreign key yang membereskannya
 * saat produk atau layanannya dihapus. Barisnya tertinggal dan kolom
 * "Dipakai" di halaman Kategori terus menghitung barang yang sudah lenyap —
 * klinik yang baru saja mengosongkan katalognya tetap melihat angka 20 dan 30.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('categorizables')->distinct()->pluck('categorizable_type') as $type) {
            $class = Relation::getMorphedModel((string) $type) ?? $type;

            if (! class_exists($class)) {
                // Jenis yang modelnya sudah tidak ada sama sekali: barisnya
                // pasti tidak akan pernah dipakai lagi.
                DB::table('categorizables')->where('categorizable_type', $type)->delete();

                continue;
            }

            $table = (new $class)->getTable();

            DB::table('categorizables')
                ->where('categorizable_type', $type)
                ->whereNotIn('categorizable_id', DB::table($table)->select('id'))
                ->delete();
        }
    }

    public function down(): void
    {
        // Tidak dibatalkan: baris yang dibuang menunjuk ke item yang sudah
        // tidak ada, jadi tidak ada yang bisa dikembalikan.
    }
};
