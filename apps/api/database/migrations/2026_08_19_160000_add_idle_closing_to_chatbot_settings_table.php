<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penutup percakapan yang menggantung.
 *
 * Ambangnya disimpan per klinik, bukan dipatok di kode: klinik yang pasiennya
 * terbiasa membalas sambil bekerja butuh jeda lebih panjang daripada klinik
 * yang chatnya ramai. Null berarti fiturnya mati — mengirim pesan ke pasien
 * tanpa klinik pernah memintanya bukan sesuatu yang boleh menyala sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('closing_idle_minutes')->nullable()->after('allow_self_registration');
            $table->text('closing_message')->nullable()->after('closing_idle_minutes');
        });

        Schema::table('chat_messages', function (Blueprint $table): void {
            // Penanda ini yang menahan penutup kedua: tanpa itu, pesan penutup
            // sendiri menjadi "pesan terakhir dari bot" dan percakapan yang
            // sama akan ditutup lagi setiap kali scheduler berjalan.
            $table->boolean('is_closing')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_settings', function (Blueprint $table): void {
            $table->dropColumn(['closing_idle_minutes', 'closing_message']);
        });

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropColumn('is_closing');
        });
    }
};
