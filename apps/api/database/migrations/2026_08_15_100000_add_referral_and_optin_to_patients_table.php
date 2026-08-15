<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua kolom untuk insentif dan izin pesan:
 * - referred_by: staf yang membawa pasien ini; dasar bonus pasien baru.
 *   Satu pasien satu pembawa, jadi bonusnya tidak pernah dobel.
 * - whatsapp_opt_in: izin menerima pesan promosi. Default true; pasien yang
 *   minta berhenti di-nonaktifkan dan broadcast promosi menghormatinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('referred_by')->nullable()->after('notes')
                ->constrained('users')->nullOnDelete();
            $table->boolean('whatsapp_opt_in')->default(true)->after('referred_by');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by');
            $table->dropColumn('whatsapp_opt_in');
        });
    }
};
