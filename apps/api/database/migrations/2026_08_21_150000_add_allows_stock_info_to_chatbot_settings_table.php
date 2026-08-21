<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Boleh-tidaknya chatbot menyebut angka stok produk ke pasien.
     *
     * Bawaannya menyala, bukan mati: klinik yang sudah memakai chatbot hari
     * ini sudah terbiasa dengan jawaban yang menyebut stok, dan migrasi tidak
     * seharusnya diam-diam mengubah cara chatbot menjawab.
     */
    public function up(): void
    {
        Schema::table('chatbot_settings', function (Blueprint $table): void {
            $table->boolean('allows_stock_info')->default(true)->after('bookable_service_ids');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_settings', function (Blueprint $table): void {
            $table->dropColumn('allows_stock_info');
        });
    }
};
