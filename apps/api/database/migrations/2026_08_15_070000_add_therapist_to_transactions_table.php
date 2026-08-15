<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fee terapis dihitung dari transaksi, jadi transaksinya harus tahu siapa
 * yang menangani. Terpisah dari `cashier_id`: yang menerima uang belum tentu
 * yang mengerjakan tindakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('therapist_id')->nullable()->after('cashier_id')
                ->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('therapist_id');
        });
    }
};
