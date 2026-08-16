<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarif fee berubah sewaktu-waktu dan perubahannya mendadak. Sebelum ini
 * perhitungan selalu memakai tarif yang sedang aktif, jadi menaikkan fee di
 * tengah bulan ikut mengubah paruh bulan yang sudah lewat — periode yang
 * sama bisa menghasilkan angka berbeda tergantung kapan dihitung.
 *
 * Tarif kini punya masa berlaku. Admin tidak mengisi tanggal apa pun:
 * menyimpan perubahan menutup tarif lama pada detik itu dan membuka tarif
 * baru, sehingga riwayatnya terbentuk sendiri dan bulan lalu tetap dihitung
 * dengan tarif yang memang berlaku waktu itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_rules', function (Blueprint $table) {
            // Null berarti tarif perdana: berlaku sejak awal sampai diubah.
            $table->timestamp('effective_from')->nullable()->after('is_active');
            // Null berarti masih berlaku sampai sekarang.
            $table->timestamp('effective_to')->nullable()->after('effective_from');

            $table->index(['tenant_id', 'effective_from', 'effective_to'], 'commission_rules_window_index');
        });

        // Aturan yang sudah ada dibiarkan tanpa tanggal mulai: null berarti
        // berlaku sejak awal. Mengisinya dengan tanggal migrasi justru
        // membuat seluruh periode sebelum hari ini kehilangan tarifnya.
    }

    public function down(): void
    {
        Schema::table('commission_rules', function (Blueprint $table) {
            $table->dropIndex('commission_rules_window_index');
            $table->dropColumn(['effective_from', 'effective_to']);
        });
    }
};
