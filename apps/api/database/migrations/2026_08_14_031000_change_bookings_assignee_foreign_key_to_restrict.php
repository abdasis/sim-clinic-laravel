<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cegah hapus permanen user yang punya booking; staf dinonaktifkan lewat
 * soft-delete sehingga jadwal historis tetap utuh.
 *
 * ponytail: SQLite tidak mendukung drop foreign key tanpa rebuild tabel,
 * jadi perubahan dilewati di sana (dipakai hanya untuk test/dev lokal).
 * Skema produksi PostgreSQL tetap mendapat RESTRICT.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['assignee_id']);
            $table->foreign('assignee_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['assignee_id']);
            $table->foreign('assignee_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
