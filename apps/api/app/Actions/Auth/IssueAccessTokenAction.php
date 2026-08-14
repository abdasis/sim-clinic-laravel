<?php

namespace App\Actions\Auth;

use App\Actions\LogAuditAction;
use App\Models\Tenant;
use App\Models\User;

/**
 * Terbitkan token Sanctum untuk sesi SPA sekaligus mencatat jejak masuknya.
 */
class IssueAccessTokenAction
{
    public function handle(User $user, ?Tenant $tenant = null, ?string $ipAddress = null): string
    {
        $token = $user->createToken('spa')->plainTextToken;

        app(LogAuditAction::class)->handle('user.login', $user, $user, [
            'ip_address' => $ipAddress,
        ], 'Pengguna '.$user->email.' berhasil masuk.', $tenant);

        return $token;
    }
}
