<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu utas percakapan WhatsApp per kontak per klinik.
 *
 * Nomor kontak yang sudah dinormalkan jadi kunci, bukan nama: pelanggan yang
 * berganti nama panggilan tetap orang yang sama, dan dua klinik boleh
 * berbicara dengan nomor yang sama tanpa saling melihat utasnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('contact_phone', 32);
            $table->string('contact_name')->nullable();
            $table->string('channel', 20);
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            // Satu nomor hanya punya satu utas per klinik.
            $table->unique(['tenant_id', 'contact_phone']);
            // Daftar kotak masuk selalu diurutkan dari pesan terbaru.
            $table->index(['tenant_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
