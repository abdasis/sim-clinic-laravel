<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk laporan bulanan.
 *
 * Laporan menyaring transaksi berdasarkan issued_at — tanggal terbitnya, yang
 * boleh dimundurkan kasir — sementara index yang ada memakai created_at. Untuk
 * klinik yang sudah berjalan setahun, selisih keduanya berarti seluruh tabel
 * dipindai setiap kali laporan dibuka.
 *
 * Rincian dana mengelompokkan pembayaran per metode setelah menggabungkannya
 * dengan transaksi. Index pada payments menutup pengelompokannya sekaligus
 * penggabungannya, jadi urutannya transaction_id dulu baru method.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->index(['tenant_id', 'issued_at'], 'transactions_tenant_issued_at_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['tenant_id', 'transaction_id', 'method'], 'payments_tenant_transaction_method_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex('transactions_tenant_issued_at_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_tenant_transaction_method_index');
        });
    }
};
