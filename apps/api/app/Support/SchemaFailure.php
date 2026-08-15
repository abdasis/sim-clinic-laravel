<?php

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * Mengenali kegagalan kueri yang penyebabnya tabel atau kolomnya memang belum
 * ada — biasanya karena database tertinggal migrasi.
 *
 * Dipisah dari bootstrap/app.php karena berkas itu dievaluasi ulang setiap
 * aplikasi di-boot, dan fungsi global di sana akan bentrok saat test menyalakan
 * aplikasi berkali-kali.
 */
final class SchemaFailure
{
    /**
     * Tiap driver melaporkannya berbeda: MySQL dan PostgreSQL lewat SQLSTATE,
     * sedangkan SQLite memakai HY000 untuk hampir semua hal sehingga hanya
     * pesannya yang membedakan.
     */
    private const CODES = [
        '42S02', // MySQL: tabel tidak ada
        '42S22', // MySQL: kolom tidak ada
        '42P01', // PostgreSQL: undefined_table
        '42703', // PostgreSQL: undefined_column
    ];

    public static function describes(QueryException $e): bool
    {
        if (in_array((string) $e->getCode(), self::CODES, true)) {
            return true;
        }

        return (bool) preg_match('/no such (table|column)/i', $e->getMessage());
    }
}
