<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alamat server gateway WhatsApp internal untuk seluruh klinik.
 *
 * Satu server dipakai bersama, seperti WahaSetting: pengelola platform yang
 * mengisinya, tiap klinik hanya memegang kredensialnya sendiri di
 * whatsapp_settings. Tanpa base_url, fitur gateway dianggap belum tersedia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_gateway_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('base_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_gateway_settings');
    }
};
