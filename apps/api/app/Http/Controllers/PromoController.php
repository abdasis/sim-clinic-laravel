<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\PromoRequest;
use App\Http\Resources\PromoResource;
use App\Models\Promo;
use App\Services\PromoService;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Promo::class);

        $params = $this->dataTableParams($request);

        $query = Promo::query()->with('items.promotable');

        Search::apply($query, 'name', $params['search']);

        $status = $params['filters']['status'] ?? 'all';

        if ($status === 'running') {
            // "Sedang berjalan" bukan kolom, melainkan status aktif yang
            // tanggalnya memuat hari ini.
            $query->activeOn();
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if (! $this->applyAllowedSort($query, $params, ['name', 'discount_value', 'status', 'starts_at', 'ends_at'])) {
            $query->orderByDesc('starts_at');
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        return response()->json([
            'data' => PromoResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function store(PromoRequest $request, PromoService $promos): JsonResponse
    {
        $this->authorize('create', Promo::class);

        $promo = $promos->create($request->validated());

        return response()->json([
            'data' => new PromoResource($promo),
            'meta' => ['message' => __('promo.created')],
        ], 201);
    }

    public function show(Promo $promo): JsonResponse
    {
        $this->authorize('view', $promo);

        return response()->json([
            'data' => new PromoResource($promo->load('items.promotable')),
            'meta' => [],
        ]);
    }

    public function update(PromoRequest $request, Promo $promo, PromoService $promos): JsonResponse
    {
        $this->authorize('update', $promo);

        $promos->update($promo, $request->validated());

        return response()->json([
            'data' => new PromoResource($promo->load('items.promotable')),
            'meta' => ['message' => __('promo.updated')],
        ]);
    }

    public function destroy(Promo $promo, PromoService $promos): JsonResponse
    {
        $this->authorize('delete', $promo);

        $promos->delete($promo);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('promo.deleted')],
        ]);
    }
}
