<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekam medis dihapus secara soft agar riwayat klinis pasien tidak pernah
 * hilang, dan booking/pasien yang sudah punya rekam medis tidak boleh
 * dihapus permanen.
 *
 * ponytail: alter FK dilewati di SQLite (tidak mendukung drop foreign key);
 * kolom soft-delete dan index tetap dibuat di kedua driver.
 */
return new class extends Migration
{
    private const COLUMNS = ['booking_id' => 'bookings', 'patient_id' => 'patients'];

    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['tenant_id', 'deleted_at']);
            // Riwayat per pasien dibaca kronologis tanpa join ke bookings.
            $table->index(['tenant_id', 'patient_id', 'created_at']);
        });

        $this->applyForeignKeys('restrictOnDelete');
    }

    public function down(): void
    {
        $this->applyForeignKeys('cascadeOnDelete');

        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'patient_id', 'created_at']);
            $table->dropIndex(['tenant_id', 'deleted_at']);
            $table->dropSoftDeletes();
        });
    }

    private function applyForeignKeys(string $rule): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::COLUMNS as $column => $table) {
            Schema::table('medical_records', function (Blueprint $blueprint) use ($column, $table, $rule): void {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)->references('id')->on($table)->{$rule}();
            });
        }
    }
};
