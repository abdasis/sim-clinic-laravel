<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alasan sebuah campaign berhenti sendiri.
 *
 * Broadcast yang dijeda gateway terbaca persis sama dengan yang dijeda admin:
 * "Dijeda", tanpa keterangan. Pada isu #320 itu berarti menatap campaign yang
 * berhenti tanpa tahu apakah ada yang perlu diperbaiki dulu sebelum
 * dilanjutkan. Null berarti dijeda orang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->string('paused_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropColumn('paused_reason');
        });
    }
};
