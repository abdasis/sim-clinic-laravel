<?php

namespace App\Actions\Invitation;

use App\Actions\LogAuditAction;
use App\Enums\ClinicRole;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\Tenant;

/**
 * Terbitkan undangan anggota tenant beserta peran kliniknya.
 */
class CreateInvitationAction
{
    public function handle(
        Tenant $tenant,
        string $email,
        string $role,
        ?ClinicRole $clinicRole = null,
    ): Invitation {
        $invitation = Invitation::create([
            'tenant_id' => $tenant->id,
            'email' => $email,
            'role' => $role,
            'clinic_role' => $clinicRole,
            'token' => Invitation::generateToken(),
            'expires_at' => Invitation::defaultExpiry(),
            'status' => InvitationStatus::Pending,
        ]);

        app(LogAuditAction::class)->handle('user.invited', $invitation, null, [
            'email' => $email,
            'role' => $role,
            'clinic_role' => $clinicRole?->value,
        ], 'Undangan dikirim ke '.$email.' sebagai '.$role
            .($clinicRole !== null ? ' dengan peran klinik '.$clinicRole->label() : '').'.', $tenant);

        return $invitation;
    }
}
