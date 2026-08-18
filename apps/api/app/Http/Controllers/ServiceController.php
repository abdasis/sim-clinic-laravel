<?php

namespace App\Http\Controllers;

use App\Enums\ServiceStatus;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\ServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\ServiceCatalogService;
use App\Support\PromoPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $params = $this->dataTableParams($request);

        // Tanpa eager load, resource memanggil relasi kategori per baris.
        $query = Service::query()->with('categories');

        if ($params['search']) {
            $query->where('name', 'like', '%'.$params['search'].'%');
        }
        // Katalog default hanya menampilkan layanan aktif; arsip diminta eksplisit.
        $status = $params['filters']['status'] ?? ServiceStatus::Active->value;

        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($params['sort']) {
            $query->orderBy($params['sort'], $params['direction']);
        } else {
            $query->latest();
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        // Satu pencari promo dipakai bersama seluruh baris halaman ini, jadi
        // katalog tidak menembak satu kueri promo per baris.
        $pricing = new PromoPricing;
        $pricing->preload(collect($page->items()));
        $request->attributes->set('promo_pricing', $pricing);

        return response()->json([
            'data' => ServiceResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function store(ServiceRequest $request, ServiceCatalogService $catalog): JsonResponse
    {
        $this->authorize('create', Service::class);

        $service = $catalog->create($request->validated());

        return response()->json([
            'data' => new ServiceResource($service),
            'meta' => ['message' => __('service.created')],
        ], 201);
    }

    public function show(Service $service): JsonResponse
    {
        $this->authorize('view', $service);

        return response()->json(['data' => new ServiceResource($service), 'meta' => []]);
    }

    public function update(ServiceRequest $request, Service $service, ServiceCatalogService $catalog): JsonResponse
    {
        $this->authorize('update', $service);

        $catalog->update($service, $request->validated());

        return response()->json([
            'data' => new ServiceResource($service),
            'meta' => ['message' => __('service.updated')],
        ]);
    }

    /**
     * Hapus permanen, berbeda dari destroy yang mengarsipkan. Dipisah jadi
     * rute sendiri supaya tindakan yang tidak bisa dibatalkan tidak pernah
     * terpanggil tanpa sengaja oleh tombol hapus yang lama.
     */
    public function forceDestroy(Service $service, ServiceCatalogService $catalog): JsonResponse
    {
        $this->authorize('delete', $service);

        $catalog->delete($service);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('service.deleted')],
        ]);
    }

    public function destroy(Service $service, ServiceCatalogService $catalog): JsonResponse
    {
        $this->authorize('delete', $service);

        $catalog->archive($service);

        return response()->json([
            'data' => new ServiceResource($service->fresh()),
            'meta' => ['message' => __('service.archived')],
        ]);
    }
}
