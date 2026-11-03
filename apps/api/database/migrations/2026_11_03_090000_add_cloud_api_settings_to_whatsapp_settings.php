<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setelan pengiriman broadcast lewat WhatsApp Cloud API Meta.
 *
 * `waba_id` menunjuk akun WhatsApp Business yang menampung template resmi
 * klinik; tanpa id itu daftar template yang sudah disetujui tidak bisa
 * ditanyakan. `reminder_template` adalah template Cloud API yang dipakai
 * pengingat otomatis, menggantikan teks bebas dari MessageTemplate.
 *
 * Unik karena satu WABA dikelola satu klinik: dua klinik yang menunjuk WABA
 * sama akan mengirim atas nama nomor yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table): void {
            $table->string('waba_id', 32)->nullable()->unique()->after('phone_number_id');
            $table->json('reminder_template')->nullable()->after('waba_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table): void {
            $table->dropUnique(['waba_id']);
            $table->dropColumn(['waba_id', 'reminder_template']);
        });
    }
};
