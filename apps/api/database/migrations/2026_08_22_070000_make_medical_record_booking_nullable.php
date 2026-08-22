<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekam medis tidak lagi mewajibkan kunjungan terjadwal (issue #319).
 *
 * Banyak kunjungan bersifat walk-in: pasien sudah terdaftar, langsung
 * ditangani, dan memaksa membuat booking formal hanya untuk menulis catatan
 * medis adalah langkah sia-sia. Booking tetap boleh ditautkan bila ada.
 *
 * Unique `[tenant_id, booking_id]` ikut dilepas: ia menjaga "satu kunjungan
 * satu rekam medis", tapi tidak lagi sahih begitu banyak baris bernilai
 * null pada kolom itu. Penjagaannya pindah ke MedicalRecordService, yang
 * memang hanya berlaku pada jalur berbooking.
 *
 * ponytail: keunikan per booking kini ditegakkan aplikasi, bukan basis data,
 * jadi dua permintaan bersamaan untuk booking yang sama secara teori masih
 * bisa lolos. Upgrade path-nya unique parsial (`WHERE booking_id IS NOT
 * NULL`) yang didukung PostgreSQL tapi tidak oleh SQLite, sehingga suite
 * dua-driver di repo ini harus disesuaikan lebih dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite membangun ulang tabel saat kolom diubah, dan proses itu
        // menjatuhkan index unique-nya sendiri — jadi dropnya hanya untuk
        // driver yang benar-benar menyimpannya terpisah.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('medical_records', function (Blueprint $table): void {
                $table->dropUnique(['tenant_id', 'booking_id']);
            });
        }

        Schema::table('medical_records', function (Blueprint $table): void {
            $table->foreignId('booking_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Baris tanpa kunjungan tidak punya tempat di skema lama; dibuang
        // lebih dulu supaya kolomnya bisa kembali NOT NULL.
        Schema::getConnection()->table('medical_records')->whereNull('booking_id')->delete();

        Schema::table('medical_records', function (Blueprint $table): void {
            $table->foreignId('booking_id')->nullable(false)->change();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('medical_records', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'booking_id']);
            });
        }
    }
};
