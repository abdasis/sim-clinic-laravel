<?php

namespace App\Services;

use App\Actions\Invitation\AcceptInvitationAction;
use App\Actions\Invitation\CancelInvitationAction;
use App\Actions\Invitation\CreateInvitationAction;
use App\Actions\Invitation\ExpireInvitationAction;
use App\Enums\ClinicRole;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;

/**
 * Undangan anggota tenant (spec 001, US4). Service hanya orkestrasi —
 * seluruh operasi tulis dijalankan lewat Action.
 */
class InvitationService
{
    public function invite(
        Tenant $tenant,
        string $email,
        string $role,
        ?string $clinicRole = null,
    ): Invitation {
        $this->guardDuplicate($tenant, $email);

        return app(CreateInvitationAction::class)->handle(
            $tenant,
            $email,
            $role,
            $clinicRole !== null ? ClinicRole::from($clinicRole) : null,
        );
    }

    public function accept(string $token, string $password): User
    {
        $invitation = $this->resolvePending($token);

        return app(AcceptInvitationAction::class)->handle($invitation, $password);
    }

    public function cancel(Invitation $invitation): Invitation
    {
        return app(CancelInvitationAction::class)->handle($invitation);
    }

    /**
     * Ambil undangan pending yang masih berlaku; yang lewat tenggat ditandai
     * kedaluwarsa lebih dulu agar tidak bisa dipakai.
     */
    public function resolvePending(string $token): Invitation
    {
        $invitation = Invitation::where('token', $token)
            ->where('status', InvitationStatus::Pending)
            ->first();

        if ($invitation === null) {
            abort(422, __('tenant.invitation_invalid'));
        }

        if (app(ExpireInvitationAction::class)->handle($invitation)) {
            abort(422, __('tenant.invitation_invalid'));
        }

        return $invitation;
    }

    /**
     * Email yang sudah jadi anggota atau masih punya undangan aktif tidak
     * boleh diundang ulang.
     */
    private function guardDuplicate(Tenant $tenant, string $email): void
    {
        $isMember = User::where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->exists();

        if ($isMember) {
            abort(422, __('tenant.email_exists'));
        }

        $hasPending = Invitation::where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '>', now())
            ->exists();

        if ($hasPending) {
            abort(422, __('tenant.invitation_pending_exists'));
        }
    }
}
