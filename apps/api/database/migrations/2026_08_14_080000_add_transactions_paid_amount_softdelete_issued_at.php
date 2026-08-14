<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transaksi menyimpan sendiri akumulasi pembayaran dan waktu terbitnya,
 * menggantikan tabel invoices terpisah. Penghapusan bersifat soft agar
 * riwayat kas tetap dapat diaudit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('paid_amount', 12, 2)->default(0)->after('subtotal');
            $table->dateTime('issued_at')->nullable()->after('payment_status');
            $table->softDeletes();
            $table->index(['tenant_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'deleted_at']);
            $table->dropSoftDeletes();
            $table->dropColumn(['paid_amount', 'issued_at']);
        });
    }
};
