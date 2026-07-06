<?php

namespace App\Services;

use App\Actions\LogAuditAction;
use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Undangan anggota tenant (spec 001, US4).
 */
class InvitationService
{
    public function invite(Tenant $tenant, string $email, string $role): Invitation
    {
        if (User::where('tenant_id', $tenant->id)->where('email', $email)->exists()) {
            abort(422, __('tenant.email_exists'));
        }

        $invitation = Invitation::create([
            'tenant_id' => $tenant->id,
            'email' => $email,
            'role' => $role,
            'token' => Invitation::generateToken(),
            'expires_at' => Invitation::defaultExpiry(),
            'status' => InvitationStatus::Pending,
        ]);

        app(LogAuditAction::class)->handle('user.invited', $invitation, null, [
            'email' => $email,
            'role' => $role,
        ], $tenant);

        return $invitation;
    }

    public function accept(string $token, string $password): User
    {
        $invitation = Invitation::where('token', $token)
            ->where('status', InvitationStatus::Pending)
            ->first();

        if (! $invitation || $invitation->isExpired()) {
            abort(422, __('tenant.invitation_invalid'));
        }

        $user = User::create([
            'tenant_id' => $invitation->tenant_id,
            'name' => Str::before($invitation->email, '@'),
            'email' => $invitation->email,
            'password' => Hash::make($password),
            'role' => $invitation->role,
            'status' => UserStatus::Active,
        ]);

        $invitation->update(['status' => InvitationStatus::Accepted]);

        app(LogAuditAction::class)->handle('user.joined', $user, $user, [
            'email' => $user->email,
        ], $invitation->tenant);

        return $user;
    }
}
