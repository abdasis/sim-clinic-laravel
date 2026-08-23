<?php

namespace App\Http\Controllers;

use App\Enums\InvitationStatus;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\InvitationRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Http\Resources\InvitationResource;
use App\Http\Resources\UserResource;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\UserService;
use App\Support\Search;
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

        Search::apply($query, ['name', 'email'], $params['search']);
        foreach (['status', 'role'] as $filter) {
            if (! empty($params['filters'][$filter])) {
                $query->where($filter, $params['filters'][$filter]);
            }
        }
        if (! $this->applyAllowedSort($query, $params, ['name', 'email', 'role', 'status', 'created_at'])) {
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
            $request->validated('clinic_role'),
        );

        return response()->json([
            'data' => ['token' => $invitation->token],
            'meta' => ['message' => __('tenant.invited')],
        ], 201);
    }

    /**
     * Undangan yang masih menunggu jawaban, untuk ditampilkan di halaman
     * manajemen pengguna. Yang sudah lewat tenggat tidak ikut terdaftar.
     */
    public function invitations(): JsonResponse
    {
        $this->assertTenantAdmin();

        $invitations = Invitation::where('tenant_id', app('tenant')->id)
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        return response()->json([
            'data' => InvitationResource::collection($invitations),
            'meta' => ['total' => $invitations->count()],
        ]);
    }

    public function cancelInvitation(Invitation $invitation, InvitationService $service): JsonResponse
    {
        $this->assertTenantAdmin();

        abort_if($invitation->tenant_id !== app('tenant')->id, 404);

        $service->cancel($invitation);

        return response()->json([
            'data' => new InvitationResource($invitation),
            'meta' => ['message' => __('tenant.invitation_cancelled')],
        ]);
    }

    public function remove(User $user, UserService $service): JsonResponse
    {
        $this->assertTenantAdmin();

        $service->remove($user);

        return response()->json([
            'data' => [],
            'meta' => ['message' => __('tenant.user_removed')],
        ]);
    }

    public function role(UpdateUserRoleRequest $request, User $user, UserService $service): JsonResponse
    {
        $this->assertTenantAdmin();

        $service->changeRole($user, $request->validated('role'));

        return response()->json([
            'data' => new UserResource($user),
            'meta' => ['message' => __('tenant.role_changed')],
        ]);
    }

    /**
     * Manajemen anggota tenant memakai permission modul staff, sama seperti
     * modul klinik lain, agar otorisasi punya satu sumber.
     */
    private function assertTenantAdmin(): void
    {
        if (! auth()->user()->can('staff.manage')) {
            abort(403, __('auth.unauthorized'));
        }
    }
}
