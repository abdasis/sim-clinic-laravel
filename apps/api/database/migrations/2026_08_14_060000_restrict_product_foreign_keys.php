<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produk diarsipkan, tidak dihapus. RESTRICT mencegah hard-delete produk
 * yang masih punya riwayat mutasi stok atau pernah terjual.
 *
 * ponytail: dilewati di SQLite (tidak mendukung drop foreign key).
 */
return new class extends Migration
{
    private const TABLES = ['stock_movements', 'transaction_items'];

    public function up(): void
    {
        $this->apply('restrictOnDelete');
    }

    public function down(): void
    {
        $this->apply('nullOnDelete');
    }

    private function apply(string $rule): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'product_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($rule): void {
                $blueprint->dropForeign(['product_id']);
                $blueprint->foreign('product_id')->references('id')->on('products')->{$rule}();
            });
        }
    }
};
