<?php

namespace App\Actions\Staff;

use App\Actions\LogAuditAction;
use App\Enums\ClinicRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ubah peran klinik staf; enum dan role spatie selalu diselaraskan bersama
 * agar otorisasi tidak pernah menyimpang dari data.
 */
class ChangeStaffRoleAction
{
    public function handle(User $staff, ClinicRole $newRole): User
    {
        $oldRole = $staff->clinic_role;

        $this->guardLastAdmin($staff, $newRole);

        DB::transaction(function () use ($staff, $newRole): void {
            $staff->update(['clinic_role' => $newRole]);
            $staff->syncRoles([$newRole->value]);
        });

        app(LogAuditAction::class)->handle('staff.role_changed', $staff, null, [
            'old' => ['clinic_role' => $oldRole?->value],
            'new' => ['clinic_role' => $newRole->value],
        ], 'Peran staf '.$staff->name.' diubah dari '.($oldRole?->label() ?? '-').' ke '.$newRole->label().'.');

        return $staff;
    }

    /**
     * Klinik tidak boleh kehilangan admin terakhirnya lewat penurunan peran.
     */
    private function guardLastAdmin(User $staff, ClinicRole $newRole): void
    {
        if ($staff->clinic_role !== ClinicRole::Admin || $newRole === ClinicRole::Admin) {
            return;
        }

        $activeAdmins = User::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('clinic_role', ClinicRole::Admin)
            ->where('status', UserStatus::Active)
            ->count();

        if ($activeAdmins <= 1) {
            abort(422, __('clinic.last_admin'));
        }
    }
}
