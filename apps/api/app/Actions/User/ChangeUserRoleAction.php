<?php

namespace App\Actions\User;

use App\Actions\LogAuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Middleware\SetPermissionTeamId;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ubah peran platform anggota tenant; role spatie global ikut diselaraskan.
 */
class ChangeUserRoleAction
{
    public function handle(User $user, UserRole $newRole): User
    {
        $oldRole = $user->role;

        $this->guardLastTenantAdmin($user, $newRole);

        DB::transaction(function () use ($user, $newRole): void {
            $user->update(['role' => $newRole]);

            app(PermissionRegistrar::class)->setPermissionsTeamId(SetPermissionTeamId::PLATFORM_TEAM_ID);
            $user->syncRoles([$newRole->value]);
        });

        app(LogAuditAction::class)->handle('user.role_changed', $user, auth()->user(), [
            'old' => ['role' => $oldRole->value],
            'new' => ['role' => $newRole->value],
        ], 'Peran pengguna '.$user->name.' ('.$user->email.') diubah dari '.$oldRole->label().' ke '.$newRole->label().'.');

        return $user;
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
}
