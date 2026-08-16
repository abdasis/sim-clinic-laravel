<?php

namespace App\Actions\Staff;

use App\Actions\LogAuditAction;
use App\Enums\ClinicRole;
use App\Models\User;

/**
 * Ubah data staf klinik: nama, email, dan perannya.
 *
 * Perubahan peran tetap dijalankan ChangeStaffRoleAction supaya penjagaan
 * admin terakhir dan penyelarasan role spatie hanya ditulis di satu tempat.
 */
class UpdateStaffAction
{
    /**
     * @param  array{name?: string, email?: string, clinic_role?: string}  $data
     */
    public function handle(User $staff, array $data): User
    {
        $profile = array_intersect_key($data, array_flip(['name', 'email']));
        $old = $staff->only(array_keys($profile));

        if ($profile !== [] && $old != $profile) {
            $staff->update($profile);

            app(LogAuditAction::class)->handle(
                'staff.updated',
                $staff,
                auth()->user(),
                ['old' => $old, 'new' => $staff->only(array_keys($profile))],
                'Memperbarui data staf '.$staff->name.'.',
            );
        }

        $newRole = isset($data['clinic_role']) ? ClinicRole::from($data['clinic_role']) : null;

        if ($newRole !== null && $newRole !== $staff->clinic_role) {
            app(ChangeStaffRoleAction::class)->handle($staff, $newRole);
        }

        return $staff->refresh();
    }
}
