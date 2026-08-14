<?php

namespace App\Actions\Invitation;

use App\Enums\InvitationStatus;
use App\Models\Invitation;

/**
 * Tandai undangan kedaluwarsa saat diakses (lazy expire), sehingga statusnya
 * jujur tanpa perlu scheduler.
 */
class ExpireInvitationAction
{
    public function handle(Invitation $invitation): bool
    {
        if ($invitation->status !== InvitationStatus::Pending || ! $invitation->isExpired()) {
            return false;
        }

        $invitation->update(['status' => InvitationStatus::Expired]);

        return true;
    }
}
