<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Poin yang ditukar jadi potongan pada nota ini, berikut nilai rupiahnya.
 *
 * Dua kolom, bukan satu: jumlah poinnya yang dikembalikan ke saldo saat nota
 * dibatalkan, sementara nilai rupiahnya yang harus tetap tercetak sama di nota
 * lama walau tarif tukarnya diubah belakangan. Menyimpan salah satunya saja
 * memaksa yang lain dihitung ulang dengan tarif hari ini — dan nota yang sudah
 * dipegang pasien berubah angkanya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedInteger('points_redeemed')->default(0)->after('points_earned');
            $table->decimal('points_redeemed_amount', 12, 2)->default(0)->after('points_redeemed');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['points_redeemed', 'points_redeemed_amount']);
        });
    }
};
