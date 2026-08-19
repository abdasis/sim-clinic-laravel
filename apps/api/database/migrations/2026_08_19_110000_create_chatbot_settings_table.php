<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setelan chatbot WhatsApp, satu baris per klinik.
 *
 * Mati secara bawaan: chatbot menjawab pasien atas nama klinik, jadi tidak
 * boleh hidup sendiri hanya karena migrasinya dijalankan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            $table->string('agent_name')->nullable();
            $table->string('agent_avatar_path')->nullable();
            // Kosong berarti seluruh layanan boleh dibooking; daftar kosong
            // dan "belum dipilih" sengaja dibedakan lewat null vs [].
            $table->json('bookable_service_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_settings');
    }
};
