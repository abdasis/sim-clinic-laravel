<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Poin yang benar-benar diberikan nota ini, disnapshot begitu lunas.
 *
 * Bukan sekadar dihitung ulang dari subtotal saat dibutuhkan: pembatalan
 * perlu tahu persis berapa yang harus ditarik kembali dari saldo pasien,
 * dan tarifnya (Rp per poin) bisa berubah belakangan — nota lama harus tetap
 * menyebut jumlah yang benar-benar diterima saat itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedInteger('points_earned')->default(0)->after('member_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('points_earned');
        });
    }
};
