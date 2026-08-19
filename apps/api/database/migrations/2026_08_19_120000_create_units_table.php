<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satuan produk sebagai data master, bukan teks bebas.
 *
 * Sebelumnya tiap produk mengetik satuannya sendiri, jadi "botol", "Botol",
 * dan "btl" hidup berdampingan di satu klinik dan tidak ada laporan yang
 * bisa menjumlahkannya.
 *
 * Statusnya memakai ServiceStatus (active/archived) seperti kategori: alur
 * "sembunyikan tanpa menghapus" persis sama, jadi tidak perlu enum kedua.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('status')->default('active');
            $table->timestamps();

            // Satu klinik tidak boleh punya dua satuan bernama sama —
            // pilihan ganda di formulir hanya jadi tebak-tebakan.
            $table->unique(['tenant_id', 'name']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
