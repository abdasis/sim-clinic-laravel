<?php

namespace App\Actions\Invitation;

use App\Actions\LogAuditAction;
use App\Actions\Tenant\SyncTenantClinicRolesAction;
use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Http\Middleware\SetPermissionTeamId;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Terima undangan: buat user, tandai undangan terpakai, dan pasang role
 * spatie sesuai peran platform + peran klinik yang tercantum di undangan.
 */
class AcceptInvitationAction
{
    public function handle(Invitation $invitation, string $password): User
    {
        return DB::transaction(function () use ($invitation, $password): User {
            $user = User::create([
                'tenant_id' => $invitation->tenant_id,
                'name' => Str::before($invitation->email, '@'),
                'email' => $invitation->email,
                'password' => Hash::make($password),
                'role' => $invitation->role,
                'status' => UserStatus::Active,
                'clinic_role' => $invitation->clinic_role,
            ]);

            $invitation->update(['status' => InvitationStatus::Accepted]);

            $this->assignRoles($invitation, $user);

            app(LogAuditAction::class)->handle('user.joined', $user, $user, [
                'email' => $user->email,
                'role' => $user->role->value,
                'clinic_role' => $user->clinic_role?->value,
            ], 'Pengguna '.$user->email.' bergabung via undangan.', $invitation->tenant);

            return $user;
        });
    }

    private function assignRoles(Invitation $invitation, User $user): void
    {
        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId(SetPermissionTeamId::PLATFORM_TEAM_ID);
        $user->assignRole($user->role->value);

        if ($user->clinic_role === null) {
            return;
        }

        // Tenant lama bisa saja belum punya role klinik saat undangan
        // diterima. Yang dipanggil hanya "pastikan perannya ada" — handle()
        // akan mengembalikan seluruh izin klinik ke bawaan, menghapus
        // pengaturan admin hanya karena ada staf baru bergabung.
        app(SyncTenantClinicRolesAction::class)->ensureRolesExist($invitation->tenant_id);

        $registrar->setPermissionsTeamId($invitation->tenant_id);
        $user->assignRole($user->clinic_role->value);
    }
}
