<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Jejak yang ditinggalkan sebuah baris master di data klinik.
 *
 * Dipakai untuk memutuskan boleh-tidaknya baris itu dihapus permanen. Sebagian
 * foreign key memakai cascade — menghapus induknya ikut menghapus data yang
 * menggantung padanya tanpa peringatan — dan sebagian lagi restrict sehingga
 * gagal dengan galat database mentah. Keduanya buruk untuk dihadapkan ke
 * pengguna, jadi jejaknya diperiksa lebih dulu.
 *
 * Cara memeriksanya sama untuk staf dan pasien, jadi tinggal di satu tempat:
 * yang membedakan hanya daftar tabelnya. Dua salinan yang dirawat terpisah
 * pasti menyimpang, dan yang menyimpang di sini berarti data ikut terhapus
 * diam-diam lewat jalur yang terlupa diperbarui.
 */
abstract class ModelReferences
{
    /** @var array<int, true> */
    private array $referenced = [];

    /** @var array<int, true> Baris yang jejaknya sudah pernah ditarik. */
    private array $loaded = [];

    /**
     * Tabel dan kolom yang menyimpan jejak.
     *
     * @return array<int, array{table: string, column: string}>
     */
    abstract protected function sources(): array;

    /**
     * Tarik jejak seluruh baris satu halaman sekaligus, bukan satu kueri per
     * baris.
     *
     * @param  Collection<int, Model>|array<int, int>  $rows
     */
    public function preload(Collection|array $rows): void
    {
        $ids = $rows instanceof Collection ? $rows->pluck('id')->all() : $rows;

        if ($ids === []) {
            return;
        }

        foreach ($ids as $id) {
            $this->loaded[(int) $id] = true;
        }

        foreach ($this->sources() as $source) {
            DB::table($source['table'])
                ->whereIn($source['column'], $ids)
                ->distinct()
                ->pluck($source['column'])
                ->each(function ($id): void {
                    $this->referenced[(int) $id] = true;
                });
        }
    }

    public function has(int $id): bool
    {
        if (! isset($this->loaded[$id])) {
            $this->preload([$id]);
        }

        return isset($this->referenced[$id]);
    }
}
