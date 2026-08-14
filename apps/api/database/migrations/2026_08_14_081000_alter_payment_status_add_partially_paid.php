<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran bisa dicicil, jadi status butuh keadaan ketiga di antara
 * belum dibayar dan lunas.
 *
 * PostgreSQL memakai CHECK constraint yang diganti isinya. SQLite tidak bisa
 * mengubah CHECK tanpa rebuild tabel, jadi kolomnya dijadikan string biasa —
 * enum PHP tetap menjaga nilainya di sana.
 */
return new class extends Migration
{
    private const STATUSES = ['unpaid', 'partially_paid', 'paid'];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->relaxSqliteColumn();

            return;
        }

        $this->replaceCheck(self::STATUSES);
    }

    public function down(): void
    {
        DB::table('transactions')
            ->where('payment_status', 'partially_paid')
            ->update(['payment_status' => 'unpaid']);

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $this->replaceCheck(['unpaid', 'paid']);
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function replaceCheck(array $statuses): void
    {
        $allowed = collect($statuses)
            ->map(fn (string $status): string => "'".$status."'::character varying")
            ->implode(', ');

        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_payment_status_check');
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_payment_status_check CHECK (payment_status::text = ANY (ARRAY[{$allowed}]::text[]))");
    }

    private function relaxSqliteColumn(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('payment_status')->default('unpaid')->change();
        });
    }
};
