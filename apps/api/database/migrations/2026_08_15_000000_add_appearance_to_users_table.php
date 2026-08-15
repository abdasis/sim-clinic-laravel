<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferensi tampilan per pengguna: warna accent, mode terang/gelap, dan
 * varian sidebar. Disimpan sebagai json (bukan jsonb) supaya suite SQLite
 * tetap bisa menjalankannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('appearance')->nullable()->after('clinic_role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('appearance');
        });
    }
};
