<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manfaat dan kegunaan layanan, dalam bentuk dokumen Tiptap.
     *
     * Terpisah dari `description` yang sudah ada: yang itu satu paragraf
     * pendek untuk katalog, yang ini teks panjang yang dibacakan chatbot saat
     * pasien bertanya "layanan ini untuk apa".
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->json('knowledge')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('knowledge');
        });
    }
};
