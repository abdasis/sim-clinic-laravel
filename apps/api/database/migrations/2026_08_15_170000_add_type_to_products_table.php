<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Barang yang sudah ada semuanya barang jual; bahan pakai baru
            // dibedakan mulai sekarang, jadi 'retail' aman sebagai bawaan.
            $table->string('type')->default('retail')->after('name');

            // Kasir menanyakan "barang yang boleh dijual" pada tiap muat
            // katalog, jadi peruntukannya diindeks bersama status.
            $table->index(['tenant_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'type', 'status']);
            $table->dropColumn('type');
        });
    }
};
