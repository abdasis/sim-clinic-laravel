<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sambungkan produk ke satuan master.
 *
 * Nullable permanen: produk lama yang satuannya tidak bisa dipetakan tetap
 * boleh ada, dan memaksanya NOT NULL berarti menolak data yang sudah telanjur
 * tersimpan. RESTRICT saat hapus — satuan yang masih dipakai produk tidak
 * boleh lenyap dan meninggalkan produk tanpa satuan.
 *
 * Kolom string `unit` sekaligus dilonggarkan jadi nullable. Kolomnya belum
 * dibuang (lihat migrasi drop yang menyusul di rilis berikutnya), tapi jalur
 * tulis baru hanya mengisi unit_id — tanpa ini setiap produk baru gagal
 * disimpan karena kolom lamanya menuntut nilai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('unit_id')
                ->nullable()
                ->after('type')
                ->constrained('units')
                ->restrictOnDelete();

            $table->index('unit_id');
        });

        // Dilonggarkan lewat ->change() dan bukan ALTER mentah supaya sama
        // jalannya di PostgreSQL maupun SQLite yang dipakai suite pengujian.
        Schema::table('products', function (Blueprint $table) {
            $table->string('unit', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // ponytail: drop FK dilewati di SQLite — SQLite tidak mendukung
            // drop constraint, dan skema produksinya PostgreSQL.
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['unit_id']);
            }

            $table->dropIndex(['unit_id']);
            $table->dropColumn('unit_id');
        });
    }
};
