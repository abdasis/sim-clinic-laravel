<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batas pesan keluar harian milik klinik ini sendiri; null berarti ikut
 * angka bawaan platform.
 *
 * Sekaligus mengindeks penghitungnya. Kuota dihitung dari pesan yang sudah
 * berangkat hari ini, dan tanpa index kueri itu memindai seluruh tabel
 * penerima — tabel yang paling cepat menggemuk di aplikasi ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->unsignedInteger('daily_message_limit')->nullable()->after('session');
        });

        Schema::table('broadcast_recipients', function (Blueprint $table) {
            $table->index(['tenant_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->dropColumn('daily_message_limit');
        });

        Schema::table('broadcast_recipients', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'sent_at']);
        });
    }
};
