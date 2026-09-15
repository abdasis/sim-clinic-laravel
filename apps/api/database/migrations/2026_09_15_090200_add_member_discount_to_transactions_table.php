<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Potongan member yang benar-benar diterima pada satu nota.
 *
 * Nama tingkatnya ikut disalin, bukan cuma ditautkan: tingkat bisa berganti
 * nama atau besaran belakangan, sedangkan nota lama wajib tetap menampilkan
 * angka dan keterangan yang sama seperti saat dicetak — sama seperti nama dan
 * harga barang yang sudah di-snapshot di transaction_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('member_tier_name')->nullable()->after('items_total');
            $table->decimal('member_discount_amount', 12, 2)->default(0)->after('member_tier_name');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['member_tier_name', 'member_discount_amount']);
        });
    }
};
