<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga normal sebelum promo, disimpan bersama harga yang benar-benar
 * dibayar.
 *
 * Tanpa ini nota tidak punya cara menunjukkan bahwa promonya terpakai:
 * yang tersimpan hanya harga akhir, sehingga pasien yang datang justru
 * karena promo memegang kertas yang tidak menyebut potongannya sama sekali.
 *
 * Nullable karena baris lama tidak bisa direka ulang — harga katalog hari
 * ini bukan harga saat transaksinya dulu dibuat. Baris tanpa nilai
 * diperlakukan sebagai "tidak ada potongan".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->decimal('list_price', 12, 2)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn('list_price');
        });
    }
};
