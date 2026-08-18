<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori terpusat untuk layanan, produk, dan pengeluaran.
 *
 * Sebelumnya masing-masing punya kolom enum sendiri, jadi klinik tidak bisa
 * menambah kategori tanpa mengubah kode. Satu tabel melayani ketiganya lewat
 * pivot polimorfik.
 *
 * Kolom `type` membatasi kategori per jenis: tanpa itu kategori layanan bisa
 * menempel ke produk, dan daftar pilihan di formulir jadi campur aduk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            // Nama kategori unik per klinik per jenis — dua "Serum" di produk
            // hanya membingungkan saat memilih di formulir.
            $table->unique(['tenant_id', 'type', 'name']);
            $table->index(['tenant_id', 'type', 'status']);
        });

        Schema::create('categorizables', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('categorizable_type');
            $table->unsignedBigInteger('categorizable_id');

            // Satu kategori per item. Kalau kelak boleh banyak, cukup lepas
            // unique ini — relasinya sendiri sudah many-to-many.
            $table->unique(['categorizable_type', 'categorizable_id']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorizables');
        Schema::dropIfExists('categories');
    }
};
