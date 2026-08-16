<?php

namespace App\Actions\Staff;

use App\Actions\LogAuditAction;
use App\Actions\Staff\Concerns\GuardsLastAdmin;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Nonaktifkan staf klinik: status jadi Inactive lalu soft-delete, sehingga
 * data yang pernah dibuat staf (booking, rekam medis, transaksi) tetap utuh.
 * Klinik tidak boleh kehilangan admin terakhirnya.
 */
class DeactivateStaffAction
{
    use GuardsLastAdmin;

    public function handle(User $staff): User
    {
        $this->guardLastAdmin($staff);

        DB::transaction(function () use ($staff): void {
            $staff->update(['status' => UserStatus::Inactive]);
            $staff->delete();
        });

        app(LogAuditAction::class)->handle(
            'staff.deactivated',
            $staff,
            null,
            ['email' => $staff->email, 'clinic_role' => $staff->clinic_role?->value],
            'Menonaktifkan staf '.$staff->name.' — peran '.($staff->clinic_role?->label() ?? '-').'.',
        );

        return $staff;
    }
}
