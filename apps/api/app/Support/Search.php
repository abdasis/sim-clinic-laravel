<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Pencarian teks yang berperilaku sama di setiap basis data.
 *
 * Dua hal yang tidak ditangani `where(..., 'like', ...)` polos:
 *
 * 1. Besar-kecil huruf. LIKE di PostgreSQL — yang dipakai produksi — peka
 *    besar-kecil huruf, jadi "facial" tidak pernah menemukan "Facial Glow".
 *    Di SQLite tidak, dan itulah sebabnya cacat ini lama tidak terlihat:
 *    suite bawaan berjalan di SQLite dan selalu hijau.
 * 2. Wildcard dari pengguna. `%` dan `_` yang diketik orang diperlakukan
 *    sebagai "apa saja", jadi mencari "diskon 50%" ikut menarik "diskon 500
 *    ribu", dan "facial_glow" juga menarik "facialXglow".
 *
 * Keduanya diselesaikan di satu tempat supaya tiap layar tidak memutuskan
 * sendiri-sendiri — sebelum ini ada tujuh belas titik pencarian dengan
 * perilaku yang sama salahnya.
 */
final class Search
{
    /**
     * Karakter pelarian pada klausa ESCAPE.
     *
     * ponytail: garis miring terbalik benar untuk PostgreSQL dan SQLite,
     * dua-duanya yang dipakai proyek ini. MySQL memperlakukan `'\'` sebagai
     * awal pelarian string sehingga menolak literalnya; kalau suatu saat
     * MySQL ikut didukung, ganti dengan karakter netral seperti `!` dan
     * lariankan juga karakter itu di pattern().
     */
    private const ESCAPE = '\\';

    /**
     * Saring $query dengan kata kunci, dicocokkan ke salah satu kolom.
     *
     * Kata kunci kosong tidak menyaring apa pun — jadi pemanggilnya tidak
     * perlu membungkusnya dengan `if` sendiri. Seluruh kolom dikurung dalam
     * satu grup supaya OR-nya tidak bocor dan membatalkan penyaring lain
     * yang sudah menempel di kueri (mis. status arsip atau tenant).
     *
     * @param  Builder<*>  $query
     * @param  array<int, string>|string  $columns
     * @return Builder<*>
     */
    public static function apply(Builder $query, array|string $columns, ?string $keyword): Builder
    {
        if (blank($keyword)) {
            return $query;
        }

        $pattern = self::pattern($keyword);

        return $query->where(function (Builder $group) use ($columns, $pattern): void {
            foreach ((array) $columns as $column) {
                $group->orWhereRaw(self::expression($group, $column), [$pattern]);
            }
        });
    }

    /**
     * Ubah kata yang diketik jadi pola LIKE yang aman.
     *
     * Karakter pelariannya dilucuti lebih dulu; kalau tidak, pelarian yang
     * baru ditambahkan untuk `%` dan `_` ikut terlarikan lagi.
     */
    public static function pattern(string $keyword): string
    {
        $escaped = str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            trim($keyword),
        );

        return '%'.$escaped.'%';
    }

    /**
     * Satu-satunya SQL mentah di jalur pencarian, dan alasannya spesifik:
     * `whereLike` bawaan Laravel sudah menurunkan ILIKE di PostgreSQL, tapi
     * tidak menyediakan klausa ESCAPE. Tanpa ESCAPE, garis miring terbalik
     * yang kita tambahkan justru dibaca SQLite sebagai huruf biasa dan
     * pencariannya berhenti menemukan apa pun.
     *
     * Kolom di-wrap lewat grammar, dan kata kuncinya tetap lewat binding —
     * tidak ada nilai pengguna yang menempel ke string SQL.
     *
     * @param  Builder<*>  $query
     */
    private static function expression(Builder $query, string $column): string
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($column);
        $escape = " escape '".self::ESCAPE."'";

        // PostgreSQL: ILIKE untuk abai besar-kecil huruf, plus cast ke text
        // supaya kolom json (label CMS multi-bahasa) ikut terbaca — persis
        // yang dilakukan whereLike bawaan Laravel.
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            return $wrapped.'::text ilike ?'.$escape;
        }

        // SQLite dan MySQL: LIKE memang sudah abai besar-kecil huruf.
        return $wrapped.' like ?'.$escape;
    }
}
