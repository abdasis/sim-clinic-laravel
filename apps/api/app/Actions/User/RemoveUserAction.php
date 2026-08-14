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
 * Cabut keanggotaan user dari tenant lewat soft-delete, dengan proteksi
 * admin terakhir (spec 001, US4). Data yang pernah dibuat user tetap utuh.
 */
class RemoveUserAction
{
    public function handle(User $user): void
    {
        // Team context wajib terpasang sebelum role spatie dibaca/dicabut.
        app(PermissionRegistrar::class)->setPermissionsTeamId(
            $user->tenant_id ?? SetPermissionTeamId::PLATFORM_TEAM_ID,
        );

        $this->guardLastTenantAdmin($user);

        DB::transaction(function () use ($user): void {
            $user->update(['status' => UserStatus::Inactive]);
            $user->delete();
        });

        app(LogAuditAction::class)->handle('user.removed', $user, auth()->user(), [
            'email' => $user->email,
        ], 'Keanggotaan pengguna '.$user->name.' ('.$user->email.') dinonaktifkan.', $user->tenant);
    }

    private function guardLastTenantAdmin(User $user): void
    {
        if ($user->role !== UserRole::TenantAdmin) {
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
