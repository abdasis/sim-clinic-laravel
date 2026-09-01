<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berapa kali sebuah campaign sudah dilanjutkan sendiri oleh penjadwal.
 *
 * Gateway yang putus-nyambung bisa memicu jeda-lanjut tanpa akhir: dijeda,
 * dilanjutkan, gagal lagi, dijeda lagi. Hitungannya yang memberi batas,
 * supaya campaign yang benar-benar bermasalah berhenti minta perhatian
 * orang alih-alih menggedor gateway selamanya. Direset begitu orang yang
 * menekan Lanjutkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->unsignedSmallInteger('auto_resumes')->default(0)->after('paused_reason');
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropColumn('auto_resumes');
        });
    }
};
