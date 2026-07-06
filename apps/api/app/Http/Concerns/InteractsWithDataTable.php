<?php

namespace App\Http\Concerns;

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
}