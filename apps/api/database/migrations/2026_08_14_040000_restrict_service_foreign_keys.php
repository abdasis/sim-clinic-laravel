<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layanan diarsipkan, tidak dihapus. RESTRICT mencegah hard-delete layanan
 * yang masih direferensi booking, treatment, atau item transaksi.
 *
 * ponytail: dilewati di SQLite (tidak mendukung drop foreign key).
 */
return new class extends Migration
{
    private const TABLES = ['bookings', 'treatment_records', 'transaction_items'];

    public function up(): void
    {
        $this->apply('restrictOnDelete');
    }

    public function down(): void
    {
        $this->apply('cascadeOnDelete');
    }

    private function apply(string $rule): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'service_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($rule): void {
                $blueprint->dropForeign(['service_id']);
                $blueprint->foreign('service_id')->references('id')->on('services')->{$rule}();
            });
        }
    }
};
