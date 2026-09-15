<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo poin loyalitas pasien.
 *
 * Disimpan sebagai satu angka berjalan, bukan dihitung ulang tiap kali dari
 * riwayat transaksi — sama seperti stok produk. Riwayat perubahannya sendiri
 * tetap tercatat di audit_logs, sehingga saldo ini adalah hasil, bukan
 * satu-satunya sumber kebenaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->unsignedInteger('loyalty_points')->default(0)->after('member_until');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('loyalty_points');
        });
    }
};
