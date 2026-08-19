<?php

namespace App\Actions\Invitation;

use App\Actions\LogAuditAction;
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

        // Tidak ada pelaku: yang memicunya adalah tautan undangan yang
        // dibuka lewat batas waktu, bukan tindakan orang di dalam klinik.
        app(LogAuditAction::class)->handle(
            'user.invitation_expired',
            $invitation,
            null,
            [
                'old' => ['status' => InvitationStatus::Pending->value],
                'new' => ['status' => InvitationStatus::Expired->value],
                'tenant_id' => $invitation->tenant_id,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ],
            'Undangan untuk '.$invitation->email.' kedaluwarsa dan ditutup otomatis.',
        );

        return true;
    }
}
