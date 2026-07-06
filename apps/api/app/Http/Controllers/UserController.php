<?php

namespace App\Http\Controllers;

use App\Actions\LogAuditAction;
use App\Actions\RemoveUserAction;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\InvitationRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->assertTenantAdmin();

        $params = $this->dataTableParams($request);

        $query = User::where('tenant_id', app('tenant')->id);

        if ($params['search']) {
            $search = $params['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }
        foreach (['status', 'role'] as $filter) {
            if (! empty($params['filters'][$filter])) {
                $query->where($filter, $params['filters'][$filter]);
            }
        }
        if ($params['sort']) {
            $query->orderBy($params['sort'], $params['direction']);
        } else {
            $query->latest();
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        return response()->json([
            'data' => UserResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function invite(InvitationRequest $request, InvitationService $service): JsonResponse
    {
        $this->assertTenantAdmin();

        $invitation = $service->invite(
            app('tenant'),
            $request->validated('email'),
            $request->validated('role'),
        );

        return response()->json([
            'data' => ['token' => $invitation->token],
            'meta' => ['message' => __('tenant.invited')],
        ], 201);
    }

    public function remove(User $user, RemoveUserAction $action): JsonResponse
    {
        $this->assertTenantAdmin();

        $action->handle($user);

        return response()->json([
            'data' => [],
            'meta' => ['message' => __('tenant.user_removed')],
        ]);
    }

    public function role(Request $request, User $user): JsonResponse
    {
        $this->assertTenantAdmin();

        $validated = $request->validate([
            'role' => ['required', 'in:member,tenant_admin'],
        ]);

        $user->update(['role' => $validated['role']]);

        app(LogAuditAction::class)->handle('user.role_changed', $user, auth()->user(), [
            'role' => $validated['role'],
        ], app('tenant'));

        return response()->json([
            'data' => new UserResource($user),
            'meta' => ['message' => __('tenant.role_changed')],
        ]);
    }

    private function assertTenantAdmin(): void
    {
        if (! auth()->user()->isTenantAdmin()) {
            abort(403, __('auth.unauthorized'));
        }
    }
}
