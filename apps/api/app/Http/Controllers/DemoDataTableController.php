<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Endpoint demo DataTable server-side.
 * Data in-memory (~200 baris) untuk verifikasi kontrak API.
 * ponytail: ganti dengan Eloquent paginateQuery saat controller real dibuat.
 */
class DemoDataTableController extends Controller
{
    use InteractsWithDataTable;

    private const ALLOWED_SORT = ['id', 'name', 'category', 'amount', 'created_at'];

    private const CATEGORIES = ['Electronics', 'Books', 'Clothing', 'Food', 'Tools'];

    public function index(Request $request): JsonResponse
    {
        $params = $this->dataTableParams($request);

        $rows = $this->buildRows();

        $rows = $this->applySearch($rows, $params['search']);
        $rows = $this->applyFilters($rows, $params['filters']);
        $rows = $this->applySort($rows, $params['sort'], $params['direction']);

        $total = $rows->count();
        $page = $params['page'];
        $perPage = $params['per_page'];
        $items = $rows->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    private function buildRows(): Collection
    {
        return collect(range(1, 200))->map(function (int $i) {
            return [
                'id' => $i,
                'name' => 'Item '.$i,
                'category' => self::CATEGORIES[($i - 1) % count(self::CATEGORIES)],
                'amount' => ($i * 137) % 5000,
                'created_at' => now()->subDays($i)->toDateTimeString(),
            ];
        });
    }

    private function applySearch(Collection $rows, ?string $search): Collection
    {
        if ($search === null || $search === '') {
            return $rows;
        }

        $term = strtolower($search);

        return $rows->filter(function (array $row) use ($term): bool {
            return str_contains(strtolower((string) $row['name']), $term)
                || str_contains(strtolower((string) $row['category']), $term);
        })->values();
    }

    private function applyFilters(Collection $rows, array $filters): Collection
    {
        foreach ($filters as $column => $value) {
            if (! in_array($column, ['category'], true)) {
                continue;
            }
            $allowed = array_map('strval', self::CATEGORIES);
            $wanted = array_values(array_filter(explode(',', (string) $value), function (string $v) use ($allowed): bool {
                return in_array($v, $allowed, true);
            }));
            if ($wanted === []) {
                continue;
            }
            $rows = $rows->filter(fn (array $row): bool => in_array((string) $row[$column], $wanted, true))->values();
        }

        return $rows;
    }

    private function applySort(Collection $rows, ?string $sort, string $direction): Collection
    {
        if ($sort === null || ! in_array($sort, self::ALLOWED_SORT, true)) {
            return $rows;
        }

        return $direction === 'desc'
            ? $rows->sortByDesc($sort)->values()
            : $rows->sortBy($sort)->values();
    }
}