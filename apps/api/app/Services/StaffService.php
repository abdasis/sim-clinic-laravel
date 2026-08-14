<?php

namespace App\Services;

use App\Actions\Staff\ChangeStaffRoleAction;
use App\Actions\Staff\CreateStaffAction;
use App\Actions\Staff\DeactivateStaffAction;
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

    public function deactivate(User $staff): User
    {
        return app(DeactivateStaffAction::class)->handle($staff);
    }
}
