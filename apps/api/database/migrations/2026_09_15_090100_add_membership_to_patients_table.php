<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keanggotaan yang sedang dipegang pasien.
 *
 * `member_until` boleh kosong — sebagian klinik menjual keanggotaan seumur
 * pemakaian, bukan berlangganan. Yang kosong berarti tidak pernah kedaluwarsa,
 * bukan sudah kedaluwarsa; bedanya menentukan apakah pasien tetap dapat
 * potongannya besok pagi.
 *
 * Tingkatnya restrictOnDelete: tingkat yang masih dipegang pasien tidak boleh
 * lenyap begitu saja, karena potongan yang sedang berjalan ikut hilang tanpa
 * ada yang tahu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('membership_tier_id')
                ->nullable()
                ->after('referred_by')
                ->constrained('membership_tiers')
                ->restrictOnDelete();
            $table->date('member_since')->nullable()->after('membership_tier_id');
            $table->date('member_until')->nullable()->after('member_since');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('membership_tier_id');
            $table->dropColumn(['member_since', 'member_until']);
        });
    }
};
