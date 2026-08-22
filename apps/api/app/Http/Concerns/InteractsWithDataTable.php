<?php

namespace App\Http\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Parser request DataTable sisi server.
 * ponytail: gunakan di controller real, panggil $this->dataTableParams($request).
 */
trait InteractsWithDataTable
{
    /**
     * Ekstrak parameter DataTable dari query request.
     *
     * @return array{
     *     page: int,
     *     per_page: int,
     *     sort: ?string,
     *     direction: 'asc'|'desc',
     *     search: ?string,
     *     filters: array<string,string>
     * }
     */
    protected function dataTableParams(Request $request): array
    {
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $filters = [];
        foreach ((array) $request->input('filter', []) as $column => $value) {
            if (is_string($column) && is_string($value) && $value !== '') {
                $filters[$column] = $value;
            }
        }

        return [
            'page' => max(1, (int) $request->integer('page', 1)),
            'per_page' => $perPage,
            'sort' => $request->filled('sort') ? (string) $request->string('sort') : null,
            'direction' => $direction,
            'search' => $request->filled('search') ? (string) $request->string('search') : null,
            'filters' => $filters,
        ];
    }

    /**
     * Terapkan urutan pilihan pengguna, bila kolomnya memang boleh diurut.
     *
     * `sort` datang mentah dari query string. Eloquent membungkus nama kolom
     * sehingga tidak ada celah injeksi, tapi kolom yang tidak ada tetap
     * dilempar basis data sebagai galat — dan sampai ke pengguna sebagai 500,
     * bukan penolakan yang bisa dibaca. Daftar kolomnya ditulis tiap
     * controller karena hanya ia yang tahu kolom apa saja yang benar-benar
     * ada di kuerinya, termasuk kolom hasil join.
     *
     * @param  array{sort: ?string, direction: 'asc'|'desc', ...}  $params
     * @param  array<int, string>  $allowed
     * @return bool true bila urutan pilihan pengguna dipakai; false berarti
     *              pemanggil perlu memasang urutan bawaannya sendiri.
     */
    protected function applyAllowedSort(Builder $query, array $params, array $allowed): bool
    {
        $sort = $params['sort'];

        if ($sort === null || ! in_array($sort, $allowed, true)) {
            return false;
        }

        $query->orderBy($sort, $params['direction']);

        return true;
    }

    /**
     * Terapkan urutan katalog: kolom pilihan pengguna, atau nama bila belum
     * memilih.
     *
     * Nama selalu diurut dari huruf kecilnya. Urutan biner bawaan basis data
     * membelah daftar jadi dua kelompok menurut besar-kecil huruf — "peeling"
     * jatuh setelah "Totok Wajah" — sehingga katalog yang seharusnya rapi
     * justru terbaca acak, baik pada urutan bawaan maupun saat penggunanya
     * menekan kepala kolom Nama.
     *
     * Arahnya sudah dinormalkan dataTableParams() jadi persis 'asc' atau
     * 'desc', jadi aman disisipkan ke ekspresi mentahnya.
     *
     * @param  array{sort: ?string, direction: 'asc'|'desc', ...}  $params
     * @param  array<int, string>  $allowed  kolom lain yang boleh diurut
     */
    protected function applyCatalogSort(Builder $query, array $params, array $allowed = []): void
    {
        $sort = $params['sort'] ?? 'name';

        if ($sort === 'name') {
            $query->orderByRaw('LOWER(name) '.$params['direction']);

            return;
        }

        if (! $this->applyAllowedSort($query, $params, $allowed)) {
            $query->orderByRaw('LOWER(name) '.$params['direction']);
        }
    }
}
