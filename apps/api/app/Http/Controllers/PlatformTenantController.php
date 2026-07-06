<?php

namespace App\Http\Controllers;

use App\Actions\LogAuditAction;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\UpdateTenantStatusRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
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

        if ($params['search']) {
            $search = $params['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }
        if (! empty($params['filters']['status'])) {
            $query->where('status', $params['filters']['status']);
        }
        if ($params['sort']) {
            $query->orderBy($params['sort'], $params['direction']);
        } else {
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

    public function status(UpdateTenantStatusRequest $request, Tenant $tenant): JsonResponse
    {
        $this->assertPlatformAdmin();

        $tenant->update(['status' => $request->validated('status')]);

        app(LogAuditAction::class)->handle('tenant.status_changed', $tenant, auth()->user(), [
            'status' => $request->validated('status'),
        ], $tenant);

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
