<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Naikkan ambang "Target penjualan" bawaan dari 0 jadi 3 juta.
 *
 * Aturan bawaannya lahir dengan min_revenue 0, artinya komisi 5% jalan sejak
 * rupiah pertama — bukan setelah target tercapai. Itu yang membuat satu baris
 * laporan berbunyi Rp11.400 padahal fee per pasien jelas Rp5.000: selisihnya
 * komisi yang tidak pernah dimaksudkan klinik untuk dibayarkan sedini itu.
 *
 * Tarif lama tidak ditimpa, melainkan ditutup dan diganti versi baru, persis
 * seperti admin mengubahnya lewat menu. Batasnya awal bulan berjalan: gaji
 * dibayar bulanan, jadi bulan yang sudah ditutup tetap dihitung dengan tarif
 * yang dipakai saat dibayarkan, sementara bulan berjalan ikut tarif baru
 * seluruhnya — bukan terbelah di tengah bulan.
 *
 * Hanya aturan yang masih persis bawaannya yang disentuh. Klinik yang sudah
 * menyetel ambangnya sendiri — berapa pun angkanya — dibiarkan apa adanya.
 */
return new class extends Migration
{
    private const NAME = 'Target penjualan';

    private const NEW_TARGET = 3_000_000;

    public function up(): void
    {
        $at = now()->startOfMonth();

        $stale = DB::table('commission_rules')
            ->where('name', self::NAME)
            ->where('type', 'revenue_percent')
            ->where('min_revenue', 0)
            ->whereNull('effective_to')
            ->get();

        foreach ($stale as $rule) {
            DB::table('commission_rules')
                ->where('id', $rule->id)
                ->update(['effective_to' => $at, 'updated_at' => now()]);

            DB::table('commission_rules')->insert([
                'tenant_id' => $rule->tenant_id,
                'name' => $rule->name,
                'therapist_id' => $rule->therapist_id,
                'type' => $rule->type,
                'amount' => $rule->amount,
                'percent' => $rule->percent,
                'min_revenue' => self::NEW_TARGET,
                'is_active' => $rule->is_active,
                'effective_from' => $at,
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Tidak dibatalkan: versi lama sudah ditutup dengan tanggal, dan
        // membukanya kembali berarti dua tarif berlaku bersamaan di periode
        // yang sama.
    }
};
