<?php

namespace App\Services;

use App\Actions\Password\ResetStaffPasswordAction;
use App\Actions\Staff\ChangeStaffRoleAction;
use App\Actions\Staff\CreateStaffAction;
use App\Actions\Staff\DeactivateStaffAction;
use App\Actions\Staff\DeleteStaffAction;
use App\Actions\Staff\UpdateStaffAction;
use App\Enums\ClinicRole;
use App\Models\Tenant;
use App\Models\User;

/**
 * Use case pengelolaan staf klinik. Orkestrasi saja — seluruh operasi
 * tulis dijalankan lewat Action.
 */
class StaffService
{
    /**
     * @param  array{name:string, email:string, password:string, clinic_role:string}  $data
     */
    public function create(Tenant $tenant, array $data): User
    {
        return app(CreateStaffAction::class)->handle($tenant, $data);
    }

    public function changeRole(User $staff, string $clinicRole): User
    {
        return app(ChangeStaffRoleAction::class)->handle($staff, ClinicRole::from($clinicRole));
    }

    /**
     * @param  array{name?:string, email?:string, clinic_role?:string}  $data
     */
    public function update(User $staff, array $data): User
    {
        return app(UpdateStaffAction::class)->handle($staff, $data);
    }

    public function deactivate(User $staff): User
    {
        return app(DeactivateStaffAction::class)->handle($staff);
    }

    public function delete(User $staff): void
    {
        app(DeleteStaffAction::class)->handle($staff);
    }

    /**
     * Setel ulang kata sandi staf. Seluruh sesinya dicabut tanpa kecuali —
     * penyetelan ulang berarti akunnya sedang dipulihkan atau diamankan.
     */
    public function resetPassword(User $staff, string $newPassword, ?User $causer = null): void
    {
        app(ResetStaffPasswordAction::class)->handle($staff, $newPassword, $causer);
    }
}
