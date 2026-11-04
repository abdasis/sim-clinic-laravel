<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bersihkan sisa jalur WhatsApp lama setelah gateway internal menjadi
 * satu-satunya jalur: server WAHA, kolom Meta, nilai template per penerima,
 * dan campaign yang terhenti karena pengirim lama.
 *
 * Tabel percakapan dan pesan TIDAK disentuh. Isinya adalah riwayat nyata yang
 * masih dibaca admin, jadi kanal lamanya hanya dipetakan ke gateway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('waha_settings');

        Schema::table('whatsapp_settings', function (Blueprint $table): void {
            $table->dropUnique(['phone_number_id']);
            $table->dropUnique(['waba_id']);
            $table->dropColumn(['phone_number_id', 'waba_id', 'reminder_template']);
        });

        Schema::table('broadcast_recipients', function (Blueprint $table): void {
            $table->dropColumn('template_values');
        });

        DB::table('conversations')
            ->whereIn('channel', ['waha', 'cloud_api'])
            ->update(['channel' => 'gateway']);

        DB::table('broadcasts')
            ->where('status', 'sending')
            ->update([
                'status' => 'paused',
                'paused_reason' => 'Migrasi ke gateway WhatsApp.',
            ]);
    }

    public function down(): void
    {
        Schema::create('waha_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('base_url')->nullable();
            $table->text('api_key')->nullable();
            $table->timestamps();
        });

        Schema::table('whatsapp_settings', function (Blueprint $table): void {
            $table->string('phone_number_id')->nullable()->unique()->after('session');
            $table->string('waba_id', 32)->nullable()->unique()->after('phone_number_id');
            $table->json('reminder_template')->nullable()->after('waba_id');
        });

        Schema::table('broadcast_recipients', function (Blueprint $table): void {
            $table->json('template_values')->nullable()->after('message');
        });

        DB::table('broadcasts')
            ->where('status', 'paused')
            ->where('paused_reason', 'Migrasi ke gateway WhatsApp.')
            ->update([
                'status' => 'sending',
                'paused_reason' => null,
            ]);
    }
};
