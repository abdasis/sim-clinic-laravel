<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cegah hard-delete pasien yang masih punya jejak layanan.
 *
 * ponytail: dilewati di SQLite (tidak mendukung drop foreign key).
 */
return new class extends Migration
{
    private const TABLES = ['bookings', 'medical_records', 'transactions'];

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
            if (! Schema::hasColumn($table, 'patient_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($rule): void {
                $blueprint->dropForeign(['patient_id']);
                $blueprint->foreign('patient_id')->references('id')->on('patients')->{$rule}();
            });
        }
    }
};
