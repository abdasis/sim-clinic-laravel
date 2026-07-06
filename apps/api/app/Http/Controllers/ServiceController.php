<?php

namespace App\Http\Controllers;

use App\Actions\ArchiveServiceAction;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\ServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $params = $this->dataTableParams($request);

        $query = Service::query();

        if ($params['search']) {
            $query->where('name', 'like', '%'.$params['search'].'%');
        }
        if (($params['filters']['status'] ?? null)) {
            $query->where('status', $params['filters']['status']);
        }
        if ($params['sort']) {
            $query->orderBy($params['sort'], $params['direction']);
        } else {
            $query->latest();
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

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

    public function store(ServiceRequest $request): JsonResponse
    {
        $this->authorize('create', Service::class);

        $service = Service::create($request->validated());

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

    public function update(ServiceRequest $request, Service $service): JsonResponse
    {
        $this->authorize('update', $service);

        $service->update($request->validated());

        return response()->json([
            'data' => new ServiceResource($service),
            'meta' => ['message' => __('service.updated')],
        ]);
    }

    public function destroy(Service $service, ArchiveServiceAction $action): JsonResponse
    {
        $this->authorize('delete', $service);

        $action->handle($service);

        return response()->json([
            'data' => new ServiceResource($service->fresh()),
            'meta' => ['message' => __('service.archived')],
        ]);
    }
}
