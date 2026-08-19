<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat percakapan chatbot per nomor pengirim.
 *
 * Dipakai sebagai konteks percakapan: sepuluh pesan terakhir dari nomor yang
 * sama ikut dikirim ke AI supaya ia tidak bertanya ulang hal yang baru saja
 * dijawab. Indeksnya mengikuti cara baca itu — per klinik, per nomor, urut
 * waktu — karena tanpa itu tiap pesan masuk menyisir seluruh tabel.
 *
 * Baris peran tool ikut disimpan supaya jawaban yang berakar pada hasil tool
 * bisa ditelusuri ulang saat ada pasien yang mengeluh salah informasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('sender_phone', 20);
            $table->enum('direction', ['in', 'out']);
            $table->text('content');
            $table->string('role')->nullable();
            $table->string('tool_name')->nullable();
            $table->string('tool_call_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'sender_phone', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
