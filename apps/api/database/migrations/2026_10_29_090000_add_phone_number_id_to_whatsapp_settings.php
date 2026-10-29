<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor WhatsApp Cloud API (Meta) milik klinik ini, bila klinik memakai
 * jalur Meta di samping sesi WAHA.
 *
 * Unik karena webhook Meta hanya menyebut nomor penerima, bukan nama sesi:
 * nomor itulah satu-satunya petunjuk klinik mana yang harus dibalas. Dua
 * klinik yang mendaftarkan nomor sama akan membuat pesan masuk tak bisa
 * dipastikan pemiliknya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->string('phone_number_id')->nullable()->unique()->after('session');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->dropUnique(['phone_number_id']);
            $table->dropColumn('phone_number_id');
        });
    }
};
