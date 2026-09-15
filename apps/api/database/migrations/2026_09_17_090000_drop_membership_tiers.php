<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus tingkat member.
 *
 * Sejak manfaat keanggotaan berjalan sepenuhnya lewat poin loyalitas, tingkat
 * member tidak lagi punya akibat apa pun: poin diberikan ke semua pasien tanpa
 * melihat tingkatnya, dan tidak ada satu pun cabang kode yang membacanya.
 * Yang tersisa cuma label di layar yang menjanjikan sesuatu yang tidak pernah
 * terjadi di kasir — lebih jujur dihapus daripada dirawat sebagai hiasan.
 *
 * Poin pasien tidak ikut terhapus: saldonya ada di kolom `loyalty_points`
 * milik pasien, bukan di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('membership_tier_id');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['member_since', 'member_until']);
        });

        Schema::dropIfExists('membership_tiers');
    }

    public function down(): void
    {
        Schema::create('membership_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('membership_tier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('member_since')->nullable();
            $table->date('member_until')->nullable();
        });
    }
};
