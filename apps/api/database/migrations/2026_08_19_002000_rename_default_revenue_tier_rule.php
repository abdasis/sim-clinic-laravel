<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aturan bawaan tingkat kedua dulu bernama "Omzet 10 juta ke atas".
 *
 * Kata omzet di situ menyesatkan: dasarnya penjualan orang itu sendiri, bukan
 * omzet klinik — dan justru dari nama itulah muncul dugaan bahwa penjualan
 * satu orang ikut menaikkan komisi semua orang.
 *
 * Hanya baris yang namanya masih persis nama bawaan yang diganti; klinik yang
 * sudah menamainya sendiri tidak disentuh.
 */
return new class extends Migration
{
    private const OLD = 'Omzet 10 juta ke atas';

    private const NEW = 'Penjualan 10 juta ke atas';

    public function up(): void
    {
        DB::table('commission_rules')->where('name', self::OLD)->update(['name' => self::NEW]);
    }

    public function down(): void
    {
        DB::table('commission_rules')->where('name', self::NEW)->update(['name' => self::OLD]);
    }
};
