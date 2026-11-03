<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template Cloud API yang dipakai sebuah broadcast.
 *
 * Pengiriman lewat Cloud API wajib memakai template resmi yang sudah
 * disetujui Meta, bukan teks bebas. Bentuknya disimpan utuh supaya penerima
 * yang dikirim belakangan tetap memakai template yang sama dengan saat
 * campaign disusun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->json('template')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->dropColumn('template');
        });
    }
};
