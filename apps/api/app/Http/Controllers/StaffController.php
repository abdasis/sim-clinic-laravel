<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Http\Requests\UpdateStaffRoleRequest;
use App\Http\Resources\StaffResource;
use App\Models\User;
use App\Services\StaffService;
use App\Support\Search;
use App\Support\StaffReferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $params = $this->dataTableParams($request);

        $query = User::where('tenant_id', app('tenant')->id)
            ->whereNotNull('clinic_role');

        Search::apply($query, ['name', 'email'], $params['search']);

        if (! $this->applyAllowedSort($query, $params, ['name', 'email', 'clinic_role', 'status', 'created_at'])) {
            $query->latest();
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        // Jejak seluruh baris halaman ini ditarik sekali, jadi kolom "boleh
        // dihapus" tidak menembak satu kueri per staf.
        $references = new StaffReferences;
        $references->preload(collect($page->items()));
        $request->attributes->set('staff_references', $references);

        return response()->json([
            'data' => StaffResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function store(StoreStaffRequest $request, StaffService $service): JsonResponse
    {
        $this->authorize('create', User::class);

        $staff = $service->create(app('tenant'), $request->validated());

        return response()->json([
            'data' => new StaffResource($staff),
            'meta' => ['message' => __('staff.created')],
        ], 201);
    }

    public function update(UpdateStaffRequest $request, User $staff, StaffService $service): JsonResponse
    {
        $this->authorize('update', $staff);

        $service->update($staff, $request->validated());

        return response()->json([
            'data' => new StaffResource($staff),
            'meta' => ['message' => __('staff.updated')],
        ]);
    }

    public function destroy(User $staff, StaffService $service): JsonResponse
    {
        $this->authorize('delete', $staff);

        $service->delete($staff);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('staff.deleted')],
        ]);
    }

    public function updateRole(UpdateStaffRoleRequest $request, User $staff, StaffService $service): JsonResponse
    {
        $this->authorize('update', $staff);

        $service->changeRole($staff, $request->validated('clinic_role'));

        return response()->json([
            'data' => new StaffResource($staff),
            'meta' => ['message' => __('staff.role_changed')],
        ]);
    }

    public function deactivate(User $staff, StaffService $service): JsonResponse
    {
        $this->authorize('deactivate', $staff);

        $service->deactivate($staff);

        return response()->json([
            'data' => new StaffResource($staff),
            'meta' => ['message' => __('staff.deactivated')],
        ]);
    }
}
