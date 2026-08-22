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
     */
    protected function applyCatalogSort(Builder $query, array $params): void
    {
        $sort = $params['sort'] ?? 'name';

        if ($sort === 'name') {
            $query->orderByRaw('LOWER(name) '.$params['direction']);

            return;
        }

        $query->orderBy($sort, $params['direction']);
    }
}
