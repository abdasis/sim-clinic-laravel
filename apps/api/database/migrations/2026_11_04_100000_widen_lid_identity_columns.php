<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lebarkan kolom identitas percakapan untuk LID.
 *
 * WhatsApp kadang hanya memberi identitas samaran ("239959873220620@lid",
 * 21 karakter) tanpa nomor asli. Identitas itu sekarang dipakai apa adanya
 * sebagai kunci percakapan dan tujuan balasan, sehingga tidak lagi muat di
 * kolom lama (varchar 20 pada chat_messages, 32 pada conversations).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->string('sender_phone', 64)->change();
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('contact_phone', 64)->change();
        });
    }

    public function down(): void
    {
        // Mengempitkan kembali kolom akan gagal bila sudah ada baris berisi LID;
        // jalankan hanya setelah baris tersebut dibersihkan.
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->string('sender_phone', 20)->change();
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('contact_phone', 32)->change();
        });
    }
};
