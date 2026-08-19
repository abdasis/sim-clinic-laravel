<?php

namespace App\Actions\User;

use App\Actions\LogAuditAction;
use App\Actions\User\Concerns\GuardsLastTenantAdmin;
use App\Enums\UserRole;
use App\Http\Middleware\SetPermissionTeamId;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ubah peran platform anggota tenant; role spatie global ikut diselaraskan.
 */
class ChangeUserRoleAction
{
    use GuardsLastTenantAdmin;

    public function handle(User $user, UserRole $newRole): User
    {
        $oldRole = $user->role;

        $this->guardLastTenantAdmin($user, $newRole);

        DB::transaction(function () use ($user, $newRole): void {
            $user->role = $newRole;
            $user->save();

            app(PermissionRegistrar::class)->setPermissionsTeamId(SetPermissionTeamId::PLATFORM_TEAM_ID);
            $user->syncRoles([$newRole->value]);
        });

        app(LogAuditAction::class)->handle('user.role_changed', $user, auth()->user(), [
            'old' => ['role' => $oldRole->value],
            'new' => ['role' => $newRole->value],
        ], 'Peran pengguna '.$user->name.' ('.$user->email.') diubah dari '.$oldRole->label().' ke '.$newRole->label().'.');

        return $user;
    }
}
