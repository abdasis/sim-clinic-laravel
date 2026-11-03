<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nilai tiap parameter template untuk satu penerima.
 *
 * Urutannya mengikuti `template.params`; dikunci saat broadcast disusun,
 * bukan dihitung ulang saat dikirim, supaya nama atau kunjungan terakhir
 * pasien tidak bergeser di antara penyusunan dan pengiriman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcast_recipients', function (Blueprint $table): void {
            $table->json('template_values')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_recipients', function (Blueprint $table): void {
            $table->dropColumn('template_values');
        });
    }
};
