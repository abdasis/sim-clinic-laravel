<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori produk retail (Facial Wash, Toner, Sunscreen, Serum, Night Cream).
 * Nullable karena produk lama belum berkategori — dibiarkan kosong lebih jujur
 * daripada ditebak masuk kategori yang salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');

            // Master produk hampir selalu difilter per kategori.
            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'category']);
            $table->dropColumn('category');
        });
    }
};
