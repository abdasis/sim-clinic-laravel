<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Pencarian kata kunci yang tidak memandang besar-kecil huruf.
 *
 * `LIKE` bawaan SQL berperilaku berbeda antar basis data: SQLite
 * menyamakan huruf besar-kecil untuk ASCII, PostgreSQL tidak. Kode yang
 * sama karena itu lolos di suite SQLite dan gagal di produksi — mencari
 * "facial" tidak menemukan "Facial Glow", dan pemakainya menyimpulkan
 * datanya tidak ada.
 *
 * Dirapikan di kedua sisi dengan LOWER(), bukan lewat operator ILIKE milik
 * PostgreSQL: memilih operator per driver berarti tes menjalankan jalur yang
 * berbeda dari produksi, dan celah itu persis tempat bug ini bersembunyi.
 * Tidak ada indeks yang dikorbankan — pola '%kata%' memang tidak bisa
 * memakai indeks biasa dengan atau tanpa LOWER().
 */
class Search
{
    /**
     * Saring kueri dengan kata kunci pada satu atau beberapa kolom.
     *
     * Beberapa kolom digabung sebagai OR di dalam satu kurung sendiri, jadi
     * ia tidak pernah melonggarkan syarat lain yang sudah menempel di kueri
     * — mis. penyaring status atau batas tenant.
     *
     * @param  array<int, string>  $columns  nama kolom dari kode, bukan dari pengguna
     */
    public static function apply(Builder $query, array $columns, ?string $keyword): void
    {
        $term = self::term($keyword);

        if ($term === null || $columns === []) {
            return;
        }

        $query->where(function (Builder $inner) use ($columns, $term): void {
            foreach ($columns as $column) {
                // wrap() mengutip identifier sesuai grammar driver-nya, jadi
                // nama kolom tidak pernah disambung mentah ke SQL.
                $wrapped = $inner->getGrammar()->wrap($column);

                $inner->orWhereRaw("LOWER({$wrapped}) LIKE ?", [$term]);
            }
        });
    }

    /**
     * Kata kunci siap pakai, atau null bila tidak ada yang perlu dicari.
     *
     * Spasi di ujung dibuang: kata kunci hasil salin tempel kerap membawanya,
     * dan '%facial %' tidak menemukan apa pun.
     */
    public static function term(?string $keyword): ?string
    {
        $keyword = trim((string) $keyword);

        return $keyword === '' ? null : '%'.mb_strtolower($keyword).'%';
    }
}
