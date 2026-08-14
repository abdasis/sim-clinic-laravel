<?php

namespace App\Actions;

use App\Enums\ClinicRole;
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

    private function guardLastAdmin(User $staff): void
    {
        if ($staff->clinic_role !== ClinicRole::Admin) {
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
