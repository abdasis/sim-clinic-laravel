<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Broadcast WhatsApp ke pasien: pengingat perawatan ulang dan promo.
 *
 * Penerima disnapshot saat broadcast dibuat — nama, nomor ternormalisasi,
 * dan pesan yang sudah dirender. Daftar pasien boleh berubah setelahnya
 * tanpa mengubah siapa yang dikirimi apa, dan statusnya bisa diaudit.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Satu tenant satu konfigurasi pengiriman: manual (tautan wa.me,
        // tanpa setup) atau gateway HTTP (Fonnte-compatible, massal).
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('driver')->default('manual');
            $table->string('api_url')->nullable();
            $table->string('api_token')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
        });

        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('audience');
            $table->json('audience_params')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            // Pasien boleh dihapus-lunak belakangan; snapshot di bawah yang
            // menjaga riwayat broadcast tetap terbaca.
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->string('name');
            $table->string('phone', 32);
            $table->text('message');
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['broadcast_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
        Schema::dropIfExists('broadcasts');
        Schema::dropIfExists('whatsapp_settings');
    }
};
