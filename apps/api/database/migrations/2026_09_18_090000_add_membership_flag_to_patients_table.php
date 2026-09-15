<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Member sebagai penanda tunggal, bukan tingkat.
 *
 * Poin hanya diberikan kepada member — itu yang membedakan member dari
 * pelanggan biasa, dan satu-satunya bedanya. Sengaja satu boolean, bukan tabel
 * tingkat seperti dulu: tingkat yang tidak punya akibat berbeda satu sama lain
 * cuma jadi label, dan itu sudah pernah dicoba lalu dihapus.
 *
 * `member_since` menandai kapan pasien didaftarkan — kosong berarti sudah
 * member sejak sebelum penanda ini ada, dan tanggal sebenarnya memang tidak
 * pernah tercatat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('is_member')->default(false)->after('referred_by');
            $table->date('member_since')->nullable()->after('is_member');
        });

        // Pasien yang sudah terlanjur mengumpulkan poin di bawah aturan lama
        // tetap member. Poinnya sudah diberikan dan sudah jadi haknya;
        // membiarkannya jadi non-member berarti saldonya berhenti tumbuh
        // tanpa ada yang pernah memutuskan itu.
        DB::table('patients')->where('loyalty_points', '>', 0)->update(['is_member' => true]);
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['is_member', 'member_since']);
        });
    }
};
