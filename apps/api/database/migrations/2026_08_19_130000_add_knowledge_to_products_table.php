<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tempat menulis informasi dan manfaat produk.
 *
 * Data produk selama ini cuma angka — harga, stok, satuan — sehingga chatbot
 * tidak punya apa pun untuk menjawab "serum ini buat apa?". Isinya dokumen
 * Tiptap JSON, sama seperti field narasi CMS, supaya admin bisa menulisnya
 * dengan judul dan daftar tanpa belajar format baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->json('knowledge')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('knowledge');
        });
    }
};
