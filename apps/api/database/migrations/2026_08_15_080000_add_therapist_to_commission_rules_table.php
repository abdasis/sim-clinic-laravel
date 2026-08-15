<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan fee bisa dibatasi ke satu terapis. Kosong berarti berlaku untuk
 * semua — perilaku lama tidak berubah. Bila terapisnya dihapus permanen,
 * aturannya ikut hilang; membiarkannya menjadi aturan global diam-diam
 * justru mengubah gaji orang lain tanpa ada yang memutuskan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_rules', function (Blueprint $table) {
            $table->foreignId('therapist_id')->nullable()->after('name')
                ->constrained('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commission_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('therapist_id');
        });
    }
};
