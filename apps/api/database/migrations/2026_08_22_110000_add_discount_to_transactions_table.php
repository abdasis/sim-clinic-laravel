<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Potongan harga di tingkat nota, di luar promo yang menempel per barang.
 *
 * Promo menjawab "layanan ini sedang diskon"; yang belum terjawab adalah
 * potongan yang diputuskan di meja kasir — pasien lama, pembayaran tunai,
 * atau kelebihan yang dibulatkan ke bawah. Selama ini kasir menyiasatinya
 * dengan mengubah harga satuan, yang membuat harga asli layanan ikut hilang
 * dari nota dan laporan.
 *
 * `subtotal` sengaja tetap berarti jumlah yang harus dibayar — seluruh
 * perhitungan pembayaran, sisa tagihan, dan status lunas sudah membacanya
 * begitu di empat belas berkas. Yang ditambahkan justru angka sebelum
 * potongan, supaya nota tetap bisa menunjukkan keduanya.
 *
 * Ketiga angka potongan disimpan, bukan dihitung ulang saat dibaca: harga
 * layanan dan promo berubah dari waktu ke waktu, dan nota lama harus tetap
 * menampilkan angka yang sama seperti saat dicetak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->decimal('items_total', 12, 2)->default(0)->after('cashier_id');
            $table->string('discount_type')->nullable()->after('items_total');
            $table->decimal('discount_value', 12, 2)->nullable()->after('discount_type');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_value');
        });

        // Nota lama tidak pernah punya potongan, jadi jumlah sebelum potongan
        // sama dengan yang dibayar. Diisi supaya baris lama tidak terbaca
        // seolah seluruhnya digratiskan.
        DB::table('transactions')->update(['items_total' => DB::raw('subtotal')]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn(['items_total', 'discount_type', 'discount_value', 'discount_amount']);
        });
    }
};
