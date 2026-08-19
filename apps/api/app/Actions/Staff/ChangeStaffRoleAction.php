<?php

namespace App\Actions\Staff;

use App\Actions\LogAuditAction;
use App\Actions\Staff\Concerns\GuardsLastAdmin;
use App\Enums\ClinicRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ubah peran klinik staf; enum dan role spatie selalu diselaraskan bersama
 * agar otorisasi tidak pernah menyimpang dari data.
 */
class ChangeStaffRoleAction
{
    use GuardsLastAdmin;

    public function handle(User $staff, ClinicRole $newRole): User
    {
        $oldRole = $staff->clinic_role;

        $this->guardLastAdmin($staff, $newRole);

        DB::transaction(function () use ($staff, $newRole): void {
            $staff->clinic_role = $newRole;
            $staff->save();
            $staff->syncRoles([$newRole->value]);
        });

        app(LogAuditAction::class)->handle('staff.role_changed', $staff, null, [
            'old' => ['clinic_role' => $oldRole?->value],
            'new' => ['clinic_role' => $newRole->value],
        ], 'Peran staf '.$staff->name.' diubah dari '.($oldRole?->label() ?? '-').' ke '.$newRole->label().'.');

        return $staff;
    }
}
