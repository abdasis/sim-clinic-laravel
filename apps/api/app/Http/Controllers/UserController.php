<?php

namespace App\Http\Controllers;

use App\Actions\LogAuditAction;
use App\Actions\User\RemoveUserAction;
use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Middleware\SetPermissionTeamId;
use App\Http\Requests\InvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Http\Resources\UserResource;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

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

        $oldRole = $user->role;
        $newRole = UserRole::from($validated['role']);

        $this->guardLastTenantAdmin($user, $newRole);

        DB::transaction(function () use ($user, $newRole): void {
            $user->update(['role' => $newRole]);

            app(PermissionRegistrar::class)->setPermissionsTeamId(SetPermissionTeamId::PLATFORM_TEAM_ID);
            $user->syncRoles([$newRole->value]);
        });

        app(LogAuditAction::class)->handle('user.role_changed', $user, auth()->user(), [
            'old_role' => $oldRole->value,
            'new_role' => $validated['role'],
        ], 'Peran pengguna '.$user->name.' ('.$user->email.') diubah dari '.$oldRole->label().' ke '.$user->role->label().'.', app('tenant'));

        return response()->json([
            'data' => new UserResource($user),
            'meta' => ['message' => __('tenant.role_changed')],
        ]);
    }

    /**
     * Tenant harus selalu punya minimal satu admin aktif.
     */
    private function guardLastTenantAdmin(User $user, UserRole $newRole): void
    {
        if ($user->role !== UserRole::TenantAdmin || $newRole === UserRole::TenantAdmin) {
            return;
        }

        $activeAdmins = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('role', UserRole::TenantAdmin)
            ->where('status', UserStatus::Active)
            ->count();

        if ($activeAdmins <= 1) {
            abort(422, __('tenant.last_admin'));
        }
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
