<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saklar pendaftaran mandiri pasien lewat chat.
 *
 * Default mati, dan itu disengaja: menyalakan chatbot berarti membuka
 * percakapan, bukan membuka pintu tulis ke tabel pasien. Klinik yang memang
 * menginginkannya menyalakannya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_settings', function (Blueprint $table) {
            $table->boolean('allow_self_registration')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_settings', function (Blueprint $table) {
            $table->dropColumn('allow_self_registration');
        });
    }
};
