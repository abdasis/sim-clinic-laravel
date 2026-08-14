<?php

namespace App\Actions\Invitation;

use App\Actions\LogAuditAction;
use App\Enums\InvitationStatus;
use App\Models\Invitation;

/**
 * Batalkan undangan yang belum diterima sehingga tokennya tidak bisa dipakai.
 */
class CancelInvitationAction
{
    public function handle(Invitation $invitation): Invitation
    {
        if ($invitation->status !== InvitationStatus::Pending) {
            abort(422, __('tenant.invitation_not_pending'));
        }

        $invitation->update(['status' => InvitationStatus::Cancelled]);

        app(LogAuditAction::class)->handle('user.invitation_cancelled', $invitation, null, [
            'email' => $invitation->email,
        ], 'Undangan untuk '.$invitation->email.' dibatalkan.', $invitation->tenant);

        return $invitation;
    }
}
