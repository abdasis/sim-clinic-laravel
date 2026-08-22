<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\UnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Services\UnitService;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD satuan produk. Bentuknya mengikuti kategori — daftar kecil, formulir
 * modal, arsip sebagai jalur utama dan hapus permanen sebagai pengecualian.
 */
class UnitController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Unit::class);

        $params = $this->dataTableParams($request);

        $query = Unit::query()->withCount('products');

        Search::apply($query, 'name', $params['search']);

        // Formulir produk hanya boleh menawarkan satuan aktif; halaman kelola
        // meminta arsipnya secara eksplisit.
        $status = $params['filters']['status'] ?? null;

        if ($status !== null && $status !== 'all') {
            $query->where('status', $status);
        }

        if (! $this->applyAllowedSort($query, $params, ['name', 'status', 'created_at'])) {
            $query->orderBy('name');
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        return response()->json([
            'data' => UnitResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function store(UnitRequest $request, UnitService $units): JsonResponse
    {
        $this->authorize('create', Unit::class);

        $unit = $units->create($request->validated());

        return response()->json([
            'data' => new UnitResource($unit),
            'meta' => ['message' => __('unit.created')],
        ], 201);
    }

    public function show(Unit $unit): JsonResponse
    {
        $this->authorize('view', $unit);

        return response()->json([
            'data' => new UnitResource($unit->loadCount('products')),
            'meta' => [],
        ]);
    }

    public function update(UnitRequest $request, Unit $unit, UnitService $units): JsonResponse
    {
        $this->authorize('update', $unit);

        $unit = $units->update($unit, $request->validated());

        return response()->json([
            'data' => new UnitResource($unit),
            'meta' => ['message' => __('unit.updated')],
        ]);
    }

    /** Arsipkan — produk yang sudah memakainya tetap menampilkan satuannya. */
    public function destroy(Unit $unit, UnitService $units): JsonResponse
    {
        $this->authorize('delete', $unit);

        $units->archive($unit);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('unit.archived')],
        ]);
    }

    public function forceDestroy(Unit $unit, UnitService $units): JsonResponse
    {
        $this->authorize('delete', $unit);

        $units->delete($unit);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('unit.deleted')],
        ]);
    }
}
