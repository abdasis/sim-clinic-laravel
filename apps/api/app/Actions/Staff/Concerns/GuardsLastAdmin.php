<?php

namespace App\Actions\Staff\Concerns;

use App\Enums\ClinicRole;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * Klinik tidak boleh kehilangan admin terakhirnya — baik lewat penurunan
 * peran, penonaktifan, maupun penghapusan. Tanpa admin, tidak ada seorang pun
 * yang bisa mengangkat admin berikutnya.
 */
trait GuardsLastAdmin
{
    /**
     * @param  ClinicRole|null  $newRole  Peran tujuan; null berarti staf akan
     *                                    hilang sama sekali (nonaktif/hapus).
     */
    protected function guardLastAdmin(User $staff, ?ClinicRole $newRole = null): void
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
