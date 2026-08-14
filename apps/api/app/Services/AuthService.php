<?php

namespace App\Services;

use App\Actions\Auth\IssueAccessTokenAction;
use App\Enums\UserStatus;
use App\Http\Middleware\SetPermissionTeamId;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Autentikasi tenant-scoped (spec 001). Staf yang dinonaktifkan
 * (soft-deleted) tidak akan pernah ditemukan di sini.
 */
class AuthService
{
    /**
     * Login admin platform — tidak terikat tenant mana pun.
     *
     * @return array{user:User, token:string}
     */
    public function loginPlatform(string $email, string $password, ?string $ipAddress = null): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(SetPermissionTeamId::PLATFORM_TEAM_ID);

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            abort(422, __('auth.invalid_credentials'));
        }

        if ($user->status === UserStatus::Inactive) {
            abort(403, __('auth.unauthorized'));
        }

        $token = app(IssueAccessTokenAction::class)->handle($user, $user->tenant, $ipAddress);

        return ['user' => $user, 'token' => $token];
    }

    /**
     * @return array{user:User, token:string}
     */
    public function login(Tenant $tenant, string $email, string $password, ?string $ipAddress = null): array
    {
        // Role/permission dibaca dalam konteks tenant yang sedang login.
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $user = User::where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            abort(422, __('auth.invalid_credentials'));
        }

        if ($user->status === UserStatus::Inactive) {
            abort(403, __('auth.unauthorized'));
        }

        $token = app(IssueAccessTokenAction::class)->handle($user, $tenant, $ipAddress);

        return ['user' => $user, 'token' => $token];
    }
}
