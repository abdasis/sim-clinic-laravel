<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pasien dan layanan yang sudah punya booking tidak boleh dihapus permanen;
 * keduanya dinonaktifkan/diarsipkan saja. tenant_id tetap cascade karena
 * menghapus tenant memang berarti menghapus seluruh datanya.
 *
 * ponytail: dilewati di SQLite (tidak mendukung drop foreign key).
 */
return new class extends Migration
{
    private const COLUMNS = ['patient_id' => 'patients', 'service_id' => 'services'];

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

        foreach (self::COLUMNS as $column => $table) {
            Schema::table('bookings', function (Blueprint $blueprint) use ($column, $table, $rule): void {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)->references('id')->on($table)->{$rule}();
            });
        }
    }
};
