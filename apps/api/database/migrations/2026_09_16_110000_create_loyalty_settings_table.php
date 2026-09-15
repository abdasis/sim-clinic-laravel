<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarif poin loyalitas per klinik.
 *
 * Sebelumnya tetap untuk semua klinik, dan itu keputusan yang tidak pernah
 * pantas dipegang kode: seberapa murah hati program poin adalah urusan margin
 * tiap klinik, bukan urusan rilis. Satu baris per tenant; klinik yang belum
 * pernah menyetelnya memakai bawaan di model.
 *
 * Nota tidak ikut berubah saat tarifnya diubah — poin dan nilai rupiahnya
 * sudah disnapshot di kolom transaksi sejak awal, jadi nota lama tetap
 * menyebut angka yang benar-benar berlaku saat ia terbit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Rupiah belanja untuk mendapat satu poin.
            $table->decimal('earn_rate', 12, 2)->default(10000);
            // Rupiah potongan dari satu poin yang ditukar.
            $table->decimal('redeem_rate', 12, 2)->default(1000);
            $table->unsignedInteger('min_redeem')->default(10);
            $table->timestamps();

            // Satu setelan per klinik; baris kedua berarti ada dua tarif yang
            // sama sahnya dan tidak ada yang bisa memutuskan mana yang dipakai.
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
    }
};
