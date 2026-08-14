<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pasien dan booking yang sudah punya transaksi tidak boleh dihapus
 * permanen — jejak kas harus tetap merujuk ke sumbernya.
 *
 * ponytail: dilewati di SQLite (tidak mendukung drop foreign key).
 */
return new class extends Migration
{
    private const COLUMNS = ['patient_id' => 'patients', 'booking_id' => 'bookings'];

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
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::COLUMNS as $column => $table) {
            Schema::table('transactions', function (Blueprint $blueprint) use ($column, $table, $rule): void {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)->references('id')->on($table)->{$rule}();
            });
        }
    }
};
