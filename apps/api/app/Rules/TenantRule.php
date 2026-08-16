<?php

namespace App\Rules;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Aturan `exists` yang terikat tenant aktif.
 *
 * `exists:` bawaan Laravel berjalan lewat query builder, bukan Eloquent,
 * sehingga `TenantScope` tidak ikut berlaku. Yang diperiksa hanya "ID ini ada
 * di tabel" — bukan "ID ini milik klinik ini". Karena ID-nya angka berurutan,
 * kasir satu klinik bisa menyebut ID milik klinik lain dan barisnya tersimpan:
 * transaksi menunjuk pasien tenant lain, nama pasiennya kosong saat
 * ditampilkan, dan klinik pemilik tidak bisa lagi menghapus pasien itu karena
 * FK-nya restrict.
 *
 * Dipakai untuk setiap kolom relasi yang menunjuk tabel bertenant.
 */
final class TenantRule
{
    /**
     * Kolom relasi wajib menunjuk baris milik tenant yang sedang aktif.
     *
     * Saat tidak ada tenant terikat (CLI, seeding, rute central), aturannya
     * gagal tertutup: rute yang memakai ini semuanya bertenant, jadi tenant
     * yang hilang menandakan salah pakai — bukan alasan melonggarkan.
     */
    public static function exists(string $table, string $column = 'id'): Exists
    {
        $tenantId = app()->bound('tenant') ? app('tenant')->id : null;

        return Rule::exists($table, $column)->where('tenant_id', $tenantId);
    }
}
