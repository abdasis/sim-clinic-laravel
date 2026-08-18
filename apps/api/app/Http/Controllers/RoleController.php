<?php

namespace App\Http\Controllers;

use App\Actions\Tenant\SyncTenantClinicRolesAction;
use App\Enums\ClinicRole;
use App\Http\Requests\ResetRoleRequest;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

/**
 * Pengaturan izin peran klinik.
 *
 * Bukan daftar berhalaman melainkan satu matriks kecil (peran x modul), jadi
 * dikirim utuh sekali jalan.
 */
class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        return response()->json([
            'data' => $this->matrix(),
            'meta' => [],
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, RoleService $roles): JsonResponse
    {
        $roles->updatePermissions($request->user(), $request->roleName(), $request->modules());

        return response()->json([
            'data' => $this->matrix(),
            'meta' => ['message' => __('role.updated')],
        ]);
    }

    public function reset(ResetRoleRequest $request, RoleService $roles): JsonResponse
    {
        $roles->resetPermissions($request->user(), $request->roleName());

        return response()->json([
            'data' => $this->matrix(),
            'meta' => ['message' => __('role.reset')],
        ]);
    }

    /**
     * Keadaan izin saat ini, dibaca dari tabel — bukan dari template di kode.
     *
     * @return array<string, mixed>
     */
    private function matrix(): array
    {
        // Kolom team disaring eksplisit: `Role::where('name', ...)` tidak
        // ikut menyaringnya, jadi tanpa ini yang terbaca bisa baris milik
        // klinik lain yang kebetulan lebih dulu.
        $roles = Role::query()
            ->with('permissions:id,name')
            ->where(config('permission.column_names.team_foreign_key'), app('tenant')->id)
            ->where('guard_name', SyncTenantClinicRolesAction::GUARD)
            ->whereIn('name', ClinicRole::values())
            ->get()
            ->keyBy('name');

        $modules = SyncTenantClinicRolesAction::modules();
        $matrix = [];

        foreach (ClinicRole::cases() as $role) {
            $granted = $roles->get($role->value)?->permissions->pluck('name')->all() ?? [];

            foreach ($modules as $module) {
                $matrix[$role->value][$module] = [
                    'view' => in_array($module.'.view', $granted, true),
                    'manage' => in_array($module.'.manage', $granted, true),
                ];
            }
        }

        return [
            'roles' => array_map(
                fn (ClinicRole $role) => ['key' => $role->value, 'label' => $role->label()],
                ClinicRole::cases(),
            ),
            'modules' => array_map(
                fn (string $module) => ['key' => $module, 'label' => __('role.module_labels.'.$module)],
                $modules,
            ),
            'matrix' => $matrix,
        ];
    }
}
