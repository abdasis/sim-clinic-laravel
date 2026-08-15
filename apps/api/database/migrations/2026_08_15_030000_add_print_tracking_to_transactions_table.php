<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nota mencantumkan cetakan ke berapa, jadi hitungannya harus benar-benar
 * disimpan — kalau tidak, setiap cetak ulang tetap mengaku cetakan pertama
 * dan jejak nota ganda hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedInteger('print_count')->default(0)->after('issued_at');
            $table->timestamp('last_printed_at')->nullable()->after('print_count');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['print_count', 'last_printed_at']);
        });
    }
};
