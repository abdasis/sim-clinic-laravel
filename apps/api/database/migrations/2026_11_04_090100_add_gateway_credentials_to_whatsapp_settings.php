<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kredensial klinik di gateway WhatsApp internal, hasil provisioning otomatis
 * saat klinik pertama kali menyiapkan sesi.
 *
 * Lima kolom rahasia disimpan terenkripsi (cast `encrypted` di model), jadi
 * tipenya text — ciphertext jauh lebih panjang daripada nilai aslinya.
 * Token webhook sengaja dibiarkan terbaca: ia bagian dari URL yang dicari
 * tanpa scope tenant, dan ciphertext acak tidak bisa dicari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table): void {
            $table->text('gateway_tenant_key')->nullable()->after('daily_message_limit');
            $table->text('gateway_api_key')->nullable()->after('gateway_tenant_key');
            $table->string('gateway_webhook_token', 64)->nullable()->unique()->after('gateway_api_key');
            $table->text('gateway_webhook_secret')->nullable()->after('gateway_webhook_token');
            $table->string('gateway_session_id')->nullable()->after('gateway_webhook_secret');
            $table->text('gateway_password')->nullable()->after('gateway_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'gateway_tenant_key',
                'gateway_api_key',
                'gateway_webhook_token',
                'gateway_webhook_secret',
                'gateway_session_id',
                'gateway_password',
            ]);
        });
    }
};
