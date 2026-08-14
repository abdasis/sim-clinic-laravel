<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris item transaksi mewakili tepat satu produk atau satu layanan.
 * Aturannya sudah dijaga FormRequest; di sini ditegakkan database supaya
 * jalur mana pun — seeder, tinker, impor — tidak bisa menembusnya.
 *
 * FK master juga naik dari nullOnDelete ke restrictOnDelete: menghapus produk
 * atau layanan tidak boleh diam-diam memutus tautan riwayat penjualan.
 * Master yang tidak dipakai lagi diarsipkan, bukan dihapus.
 *
 * ponytail: CHECK dan alter FK dilewati di SQLite (tidak mendukung
 * keduanya lewat ALTER), jadi aturan ini hanya ditegakkan di PostgreSQL.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'transaction_items_exactly_one_reference';

    private const COLUMNS = ['product_id' => 'products', 'service_id' => 'services'];

    public function up(): void
    {
        if ($this->onSqlite()) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE transaction_items ADD CONSTRAINT %s CHECK ((product_id IS NULL) <> (service_id IS NULL))',
            self::CONSTRAINT,
        ));

        $this->applyForeignKeys('restrictOnDelete');
    }

    public function down(): void
    {
        if ($this->onSqlite()) {
            return;
        }

        $this->applyForeignKeys('nullOnDelete');

        DB::statement('ALTER TABLE transaction_items DROP CONSTRAINT '.self::CONSTRAINT);
    }

    private function applyForeignKeys(string $rule): void
    {
        foreach (self::COLUMNS as $column => $table) {
            Schema::table('transaction_items', function (Blueprint $blueprint) use ($column, $table, $rule): void {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)->references('id')->on($table)->{$rule}();
            });
        }
    }

    private function onSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }
};
