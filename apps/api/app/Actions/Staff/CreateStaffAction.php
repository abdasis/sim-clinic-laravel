<?php

namespace App\Actions\Staff;

use App\Actions\LogAuditAction;
use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Tambah staf klinik beserta role spatie-nya, supaya staf baru langsung
 * punya akses modul sesuai perannya.
 */
class CreateStaffAction
{
    /**
     * @param  array{name:string, email:string, password:string, clinic_role:string}  $data
     */
    public function handle(Tenant $tenant, array $data): User
    {
        $clinicRole = ClinicRole::from($data['clinic_role']);

        $staff = DB::transaction(function () use ($tenant, $data, $clinicRole): User {
            $staff = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => UserRole::Member,
                'status' => UserStatus::Active,
                'clinic_role' => $clinicRole,
            ]);

            $staff->syncRoles([$clinicRole->value]);

            return $staff;
        });

        app(LogAuditAction::class)->handle('staff.created', $staff, null, [
            'name' => $staff->name,
            'email' => $staff->email,
            'clinic_role' => $clinicRole->value,
        ], 'Staf baru '.$staff->name.' ('.$staff->email.') ditambahkan sebagai '.$clinicRole->label().'.', $tenant);

        return $staff;
    }
}
