<?php

namespace App\Services;

use App\Actions\User\ChangeUserRoleAction;
use App\Actions\User\RemoveUserAction;
use App\Enums\UserRole;
use App\Models\User;

/**
 * Use case pengelolaan anggota tenant (bukan peran klinik).
 */
class UserService
{
    public function changeRole(User $user, string $role): User
    {
        return app(ChangeUserRoleAction::class)->handle($user, UserRole::from($role));
    }

    public function remove(User $user): void
    {
        app(RemoveUserAction::class)->handle($user);
    }
}
