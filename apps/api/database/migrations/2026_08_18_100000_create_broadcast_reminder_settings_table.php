<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan follow-up otomatis tingkat klinik.
 *
 * Berbeda dari `reminder_rules` yang mengikat satu layanan ke satu ambang
 * hari: yang ini satu ambang untuk seluruh klinik, dihitung dari rekam medis
 * terakhir pasien, bukan dari transaksi POS.
 *
 * Satu baris per klinik — `tenant_id` unik, bukan sekadar berindeks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_reminder_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('days');
            $table->foreignId('message_template_id')->nullable()->constrained()->nullOnDelete();
            // Mati sampai admin menyalakannya: mengaktifkan pengiriman ke
            // pasien sungguhan tidak boleh jadi efek samping dari migrasi.
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_reminder_settings');
    }
};
