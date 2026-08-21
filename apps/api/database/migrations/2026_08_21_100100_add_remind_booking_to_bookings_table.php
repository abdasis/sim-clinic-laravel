<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda per booking: pasien ini mau diingatkan atau tidak.
 *
 * Mati secara bawaan dan sengaja terpisah dari saklar global. Tidak semua
 * jadwal pantas diingatkan — kontrol singkat, kunjungan yang pasiennya memang
 * sudah di tempat — dan admin yang paling tahu bedanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->boolean('remind_booking')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('remind_booking');
        });
    }
};
