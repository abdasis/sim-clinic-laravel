<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jam buka-tutup klinik per hari.
 *
 * Selama ini tidak pernah tersimpan di mana pun, sehingga chatbot terpaksa
 * menjawab "belum tersedia" untuk pertanyaan paling sering ditanya pasien —
 * dan menawarkan jadwal di hari klinik tutup.
 *
 * Satu rentang per hari, bukan banyak: klinik ini buka lurus tanpa jam
 * istirahat. Bentuk JSON-nya bisa diperluas jadi banyak rentang tanpa mengubah
 * kolomnya kalau nanti dibutuhkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profile_settings', function (Blueprint $table): void {
            $table->json('operating_hours')->nullable()->after('tagline');
        });
    }

    public function down(): void
    {
        Schema::table('company_profile_settings', function (Blueprint $table): void {
            $table->dropColumn('operating_hours');
        });
    }
};
