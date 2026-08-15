<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Nullable: layanan lama tidak punya kategori, dan menebak
            // kategorinya lebih menyesatkan daripada membiarkannya kosong.
            $table->string('category')->nullable()->after('name');

            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'category']);
            $table->dropColumn('category');
        });
    }
};
