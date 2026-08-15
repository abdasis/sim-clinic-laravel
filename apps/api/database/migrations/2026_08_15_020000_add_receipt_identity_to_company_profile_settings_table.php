<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kop nota butuh alamat, tagline, dan catatan kaki (mis. keterangan PPN).
 * Ketiganya milik identitas klinik, jadi tempatnya menyatu dengan logo di
 * setelan profil perusahaan — bukan dikunci di kode supaya tiap tenant
 * mencetak notanya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profile_settings', function (Blueprint $table) {
            $table->text('address')->nullable()->after('site_name');
            $table->string('tagline')->nullable()->after('address');
            $table->string('receipt_note')->nullable()->after('tagline');
        });
    }

    public function down(): void
    {
        Schema::table('company_profile_settings', function (Blueprint $table) {
            $table->dropColumn(['address', 'tagline', 'receipt_note']);
        });
    }
};
