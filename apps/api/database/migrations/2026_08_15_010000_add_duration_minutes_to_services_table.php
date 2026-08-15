<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Default 30 menit supaya baris lama tidak null; durasi tidak
            // pernah negatif sehingga unsignedInteger sudah cukup.
            $table->unsignedInteger('duration_minutes')->default(30)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
