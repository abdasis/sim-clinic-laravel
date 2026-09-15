<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tingkat member berhenti memotong harga; manfaatnya sekarang poin
 * loyalitas saja, bukan dua mekanisme sekaligus.
 *
 * Klinik sempat mencoba keduanya berdampingan dan memilih satu: potongan
 * otomatis ternyata cuma menambah satu lapis hitungan lagi di kasir,
 * sementara poin sudah cukup sebagai manfaat keanggotaan. Tingkat member
 * jadi murni label (nama, keterangan, status) — dasar untuk klasifikasi
 * dan manfaat lain di masa depan, tanpa mengubah tagihan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_tiers', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'stacks_with_promo']);
        });
    }

    public function down(): void
    {
        Schema::table('membership_tiers', function (Blueprint $table) {
            $table->string('discount_type')->nullable();
            $table->decimal('discount_value', 8, 2)->nullable();
            $table->boolean('stacks_with_promo')->default(false);
        });
    }
};
