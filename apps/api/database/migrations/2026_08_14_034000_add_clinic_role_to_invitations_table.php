<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Undangan dapat menetapkan peran klinik penerima sejak awal, sehingga staf
 * baru langsung punya akses modul yang sesuai saat menerima undangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->enum('clinic_role', ['admin', 'doctor', 'therapist', 'cashier'])
                ->nullable()
                ->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('clinic_role');
        });
    }
};
