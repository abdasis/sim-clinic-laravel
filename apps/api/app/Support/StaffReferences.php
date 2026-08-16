<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Jejak yang ditinggalkan seorang staf di data klinik.
 *
 * Dipakai untuk memutuskan boleh-tidaknya sebuah akun dihapus permanen.
 * Sebagian foreign key ke tabel users memakai cascade — menghapus staf yang
 * pernah bekerja akan ikut menghapus booking dan rekam medisnya tanpa
 * peringatan apa pun. Karena itu jejaknya diperiksa lebih dulu, bukan
 * dipasrahkan ke database.
 *
 * audit_logs sengaja tidak dihitung: kolomnya null saat penghapusan dan
 * narasi lognya sudah memuat nama, jadi jejak sistemnya tidak hilang. Kalau
 * ikut dihitung, staf yang sekadar pernah login pun tidak akan pernah bisa
 * dihapus.
 */
class StaffReferences
{
    /**
     * @var array<int, array{table: string, column: string}>
     */
    private const SOURCES = [
        ['table' => 'bookings', 'column' => 'assignee_id'],
        ['table' => 'medical_records', 'column' => 'author_id'],
        ['table' => 'transactions', 'column' => 'cashier_id'],
        ['table' => 'transaction_performers', 'column' => 'user_id'],
        ['table' => 'transaction_items', 'column' => 'offered_by'],
        ['table' => 'expenses', 'column' => 'recorded_by'],
        ['table' => 'commission_rules', 'column' => 'therapist_id'],
        ['table' => 'broadcasts', 'column' => 'created_by'],
        ['table' => 'patients', 'column' => 'referred_by'],
    ];

    /** @var array<int, true> */
    private array $referenced = [];

    /** @var array<int, true> Staf yang jejaknya sudah pernah ditarik. */
    private array $loaded = [];

    /**
     * Kumpulkan jejak seluruh baris satu halaman sekaligus, bukan satu kueri
     * per staf.
     *
     * @param  Collection<int, User>|array<int, int>  $staff
     */
    public function preload(Collection|array $staff): void
    {
        $ids = $staff instanceof Collection
            ? $staff->pluck('id')->all()
            : $staff;

        if ($ids === []) {
            return;
        }

        foreach ($ids as $id) {
            $this->loaded[(int) $id] = true;
        }

        foreach (self::SOURCES as $source) {
            DB::table($source['table'])
                ->whereIn($source['column'], $ids)
                ->distinct()
                ->pluck($source['column'])
                ->each(function ($id): void {
                    $this->referenced[(int) $id] = true;
                });
        }
    }

    public function has(int $staffId): bool
    {
        if (! isset($this->loaded[$staffId])) {
            $this->preload([$staffId]);
        }

        return isset($this->referenced[$staffId]);
    }
}
