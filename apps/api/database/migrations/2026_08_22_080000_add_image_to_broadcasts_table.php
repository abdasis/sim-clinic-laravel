<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Broadcast boleh membawa satu gambar.
 *
 * Promo klinik hampir selalu berupa poster; tanpa ini admin mengirim
 * posternya manual satu per satu lewat WhatsApp, dan daftar penerima yang
 * sudah disusun rapi di sini jadi tidak terpakai.
 *
 * Satu gambar per broadcast, bukan banyak: WhatsApp mengirim caption hanya
 * pada gambar pertama, jadi kiriman kedua dan seterusnya akan sampai tanpa
 * teks apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->string('image_path')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->dropColumn('image_path');
        });
    }
};
