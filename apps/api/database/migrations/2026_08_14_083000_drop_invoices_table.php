<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice bukan entitas terpisah — satu transaksi selalu satu invoice.
 * Waktu terbitnya dipindah ke kolom transactions.issued_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        // Pindahkan waktu terbit sebelum tabelnya hilang.
        DB::table('invoices')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('transactions')
                    ->where('id', $row->transaction_id)
                    ->whereNull('issued_at')
                    ->update(['issued_at' => $row->issued_at]);
            }
        });

        Schema::drop('invoices');
    }

    public function down(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->dateTime('issued_at')->nullable();
            $table->timestamps();
        });
    }
};
