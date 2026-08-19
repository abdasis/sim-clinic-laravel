<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Halaman Log Aktivitas selalu membaca tabel ini terurut waktu dan disaring
 * rentang tanggal, sementara satu-satunya indeks yang ada menyangkut modul
 * dan pelaku. Tanpa indeks waktu, tiap pembukaan halaman memindai seluruh
 * tabel yang justru tumbuh paling cepat di antara semua tabel klinik.
 *
 * ponytail: penyaring tenant masih memindai properties->tenant_id; indeks
 * ekspresi JSON menyusul kalau satu klinik sudah menyimpan ratusan ribu
 * baris (docs/erd/audit_logs.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
