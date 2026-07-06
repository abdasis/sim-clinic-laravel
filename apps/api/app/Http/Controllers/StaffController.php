<?php

namespace App\Http\Controllers;

use App\Actions\LogAuditAction;
use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRoleRequest;
use App\Http\Resources\StaffResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $params = $this->dataTableParams($request);

        $query = User::where('tenant_id', app('tenant')->id)
            ->whereNotNull('clinic_role');

        if ($params['search']) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($params['sort']) {
            $query->orderBy($params['sort'], $params['direction']);
        } else {
            $query->latest();
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

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

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $staff = User::create([
            'tenant_id' => app('tenant')->id,
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => $request->validated('clinic_role'),
        ]);

        app(LogAuditAction::class)->handle('staff.created', $staff);

        return response()->json([
            'data' => new StaffResource($staff),
            'meta' => ['message' => __('staff.created')],
        ], 201);
    }

    public function updateRole(UpdateStaffRoleRequest $request, User $staff): JsonResponse
    {
        $this->authorize('update', $staff);

        $newRole = ClinicRole::from($request->validated('clinic_role'));

        if ($staff->clinic_role === ClinicRole::Admin && $newRole !== ClinicRole::Admin) {
            $adminCount = User::where('tenant_id', app('tenant')->id)
                ->where('clinic_role', ClinicRole::Admin)
                ->count();

            if ($adminCount <= 1) {
                abort(422, __('clinic.last_admin'));
            }
        }

        $staff->update(['clinic_role' => $newRole]);

        app(LogAuditAction::class)->handle('staff.role_changed', $staff);

        return response()->json([
            'data' => new StaffResource($staff),
            'meta' => ['message' => __('staff.role_changed')],
        ]);
    }

    public function deactivate(User $staff): JsonResponse
    {
        $this->authorize('deactivate', $staff);

        if ($staff->clinic_role === ClinicRole::Admin) {
            $activeAdminCount = User::where('tenant_id', app('tenant')->id)
                ->where('clinic_role', ClinicRole::Admin)
                ->where('status', UserStatus::Active)
                ->count();

            if ($activeAdminCount <= 1) {
                abort(422, __('clinic.last_admin'));
            }
        }

        $staff->update(['status' => UserStatus::Inactive]);

        app(LogAuditAction::class)->handle('staff.deactivated', $staff);

        return response()->json([
            'data' => new StaffResource($staff),
            'meta' => ['message' => __('staff.deactivated')],
        ]);
    }
}
