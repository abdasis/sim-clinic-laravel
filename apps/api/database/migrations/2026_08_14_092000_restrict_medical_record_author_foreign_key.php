<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menghapus akun dokter tidak boleh ikut menghapus rekam medis yang pernah
 * ditulisnya — staf yang keluar dinonaktifkan, bukan dihapus permanen.
 *
 * ponytail: alter FK dilewati di SQLite (tidak mendukung drop foreign key).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->applyForeignKey('restrictOnDelete');
    }

    public function down(): void
    {
        $this->applyForeignKey('cascadeOnDelete');
    }

    private function applyForeignKey(string $rule): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('medical_records', function (Blueprint $table) use ($rule): void {
            $table->dropForeign(['author_id']);
            $table->foreign('author_id')->references('id')->on('users')->{$rule}();
        });
    }
};
