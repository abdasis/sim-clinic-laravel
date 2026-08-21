<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setelan pengingat jadwal booking, satu baris per klinik.
 *
 * Berbeda dari BroadcastReminderSetting yang menghitung mundur dari kunjungan
 * terakhir pasien: yang ini menghitung maju dari jadwal yang sudah ada, supaya
 * pasien diingatkan sebelum datang.
 *
 * Mati secara bawaan — pesan yang keluar atas nama klinik tidak boleh hidup
 * sendiri hanya karena migrasinya dijalankan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_reminder_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            // Satu jam sebelum jadwal: cukup untuk berangkat, belum cukup lama
            // untuk dilupakan lagi.
            $table->unsignedInteger('offset_minutes')->default(60);
            $table->foreignId('message_template_id')->nullable()
                ->constrained('message_templates')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_reminder_settings');
    }
};
