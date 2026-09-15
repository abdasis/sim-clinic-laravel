<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyertai penghapusan potongan otomatis member (lihat migrasi tingkat
 * member): tidak ada lagi angka potongan untuk disnapshot ke nota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['member_tier_name', 'member_discount_amount']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('member_tier_name')->nullable()->after('items_total');
            $table->decimal('member_discount_amount', 12, 2)->default(0)->after('member_tier_name');
        });
    }
};
