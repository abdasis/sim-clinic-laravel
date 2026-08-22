<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teks konfirmasi booking ikut bisa disunting klinik, bukan terkunci di kode.
 *
 * Ditumpangkan ke tabel setelan pengingat, bukan tabel baru: keduanya adalah
 * satu setelan per klinik tentang pesan otomatis seputar booking, dan
 * memisahkannya hanya menambah satu kueri untuk data yang selalu dibaca
 * bersamaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_reminder_settings', function (Blueprint $table) {
            // Nullable dan tanpa nilai bawaan: klinik yang belum memilih tetap
            // memakai teks bawaan aplikasi, bukan pesan kosong.
            $table->foreignId('confirmation_template_id')
                ->nullable()
                ->after('message_template_id')
                ->constrained('message_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_reminder_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmation_template_id');
        });
    }
};
