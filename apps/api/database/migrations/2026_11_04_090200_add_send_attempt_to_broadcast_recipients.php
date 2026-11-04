<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penghitung percobaan kirim per penerima broadcast.
 *
 * Dipakai sebagai bagian kunci idempotensi ke gateway: penerima yang dikirim
 * ulang setelah gagal memakai nomor percobaan baru, sehingga gateway tidak
 * menganggapnya duplikat pesan lama. Berbeda dari `attempts`, yang menghitung
 * kegagalan pengiriman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcast_recipients', function (Blueprint $table): void {
            $table->unsignedInteger('send_attempt')->default(0)->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_recipients', function (Blueprint $table): void {
            $table->dropColumn('send_attempt');
        });
    }
};
