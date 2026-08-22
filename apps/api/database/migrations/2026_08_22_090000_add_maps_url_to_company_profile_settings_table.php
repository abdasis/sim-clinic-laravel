<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan Google Maps klinik.
 *
 * Alamat tertulis saja menyisakan pekerjaan di sisi pasien: ia harus
 * mengetik ulang alamatnya ke aplikasi peta, dan nama komplek yang mirip
 * membuatnya berakhir di jalan yang salah. Satu tautan menghapus langkah itu.
 *
 * Disimpan sebagai setelan per klinik, bukan konstanta: tiap klinik punya
 * titiknya sendiri, dan alamat yang salah lebih buruk daripada tidak ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profile_settings', function (Blueprint $table): void {
            $table->string('maps_url')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('company_profile_settings', function (Blueprint $table): void {
            $table->dropColumn('maps_url');
        });
    }
};
