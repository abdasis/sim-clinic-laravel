<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran pada pesan keluar: jenis, path disk `public`, mime, dan nama asli.
 * Semuanya nullable — pesan teks tidak punya lampiran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('media_type', 20)->nullable()->after('body');
            $table->string('media_path')->nullable()->after('media_type');
            $table->string('media_mime', 100)->nullable()->after('media_path');
            $table->string('media_name', 255)->nullable()->after('media_mime');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'media_path', 'media_mime', 'media_name']);
        });
    }
};
