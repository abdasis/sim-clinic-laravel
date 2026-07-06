<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * Nonaktifkan keanggotaan user tenant, dengan proteksi admin terakhir (spec 001, US4).
 */
class RemoveUserAction
{
    public function handle(User $user): void
    {
        if ($user->role === UserRole::TenantAdmin) {
            $activeAdmins = User::where('tenant_id', $user->tenant_id)
                ->where('role', UserRole::TenantAdmin)
                ->where('status', UserStatus::Active)
                ->count();

            if ($activeAdmins <= 1) {
                abort(422, __('tenant.last_admin'));
            }
        }

        $user->update(['status' => UserStatus::Inactive]);

        app(LogAuditAction::class)->handle('user.removed', $user, auth()->user(), [
            'email' => $user->email,
        ], $user->tenant);
    }
}
