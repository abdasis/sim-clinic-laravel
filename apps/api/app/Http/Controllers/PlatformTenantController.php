<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\UpdateTenantStatusRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\PlatformTenantService;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformTenantController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin();

        $params = $this->dataTableParams($request);

        $query = Tenant::query();

        Search::apply($query, ['name', 'slug', 'phone'], $params['search']);

        if (! empty($params['filters']['status'])) {
            $query->where('status', $params['filters']['status']);
        }
        if (! $this->applyAllowedSort($query, $params, ['name', 'slug', 'phone', 'status', 'created_at'])) {
            $query->latest();
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        return response()->json([
            'data' => TenantResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function status(UpdateTenantStatusRequest $request, Tenant $tenant, PlatformTenantService $service): JsonResponse
    {
        $this->assertPlatformAdmin();

        $service->changeStatus($tenant, $request->validated('status'));

        return response()->json([
            'data' => new TenantResource($tenant),
            'meta' => ['message' => __('tenant.status_changed')],
        ]);
    }

    private function assertPlatformAdmin(): void
    {
        if (! auth()->user()->isPlatformAdmin()) {
            abort(403, __('auth.unauthorized'));
        }
    }
}
