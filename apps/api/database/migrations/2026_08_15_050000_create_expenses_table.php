<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengeluaran klinik: sisi lawan dari transaksi kasir. Dipisah dari
 * stock_movements karena tidak semua pengeluaran berwujud barang — gaji,
 * sewa, dan fee insentif tidak menyentuh stok sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('spent_at');
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->string('note')->nullable();
            // Pencatat disimpan untuk penelusuran; stafnya boleh dinonaktifkan
            // tanpa menghapus riwayat, jadi FK-nya menahan penghapusan.
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Laporan selalu bertanya "pengeluaran bulan ini per kategori".
            $table->index(['tenant_id', 'spent_at']);
            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
