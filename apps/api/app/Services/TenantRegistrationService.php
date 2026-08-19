<?php

namespace App\Services;

use App\Actions\LogAuditAction;
use App\Actions\Tenant\SeedDefaultCommissionRulesAction;
use App\Actions\Tenant\SeedDefaultUnitsAction;
use App\Actions\Tenant\SyncTenantClinicRolesAction;
use App\Enums\ClinicRole;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Middleware\SetPermissionTeamId;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Registrasi tenant baru + admin pertama secara atomik (spec 001, US1).
 */
class TenantRegistrationService
{
    /**
     * Slug yang dipakai route platform, tidak boleh diambil tenant.
     *
     * @var array<int, string>
     */
    private const RESERVED_SLUGS = ['central'];

    /**
     * @param  array{company_name:string, phone:string, email:string, password:string}  $data
     * @return array{tenant:Tenant, user:User}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $slug = Str::slug($data['company_name']);

            if ($slug === '') {
                abort(422, __('tenant.slug_invalid'));
            }
            if (in_array($slug, self::RESERVED_SLUGS, true)) {
                abort(422, __('tenant.slug_reserved'));
            }
            if (Tenant::where('slug', $slug)->exists()) {
                abort(422, __('tenant.slug_taken'));
            }

            $tenant = Tenant::create([
                'name' => $data['company_name'],
                'slug' => $slug,
                'phone' => $data['phone'],
                'status' => TenantStatus::Active,
            ]);

            $user = new User([
                'name' => $data['company_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->tenant_id = $tenant->id;
            $user->role = UserRole::TenantAdmin;
            $user->status = UserStatus::Active;
            $user->clinic_role = ClinicRole::Admin;
            $user->save();

            $this->assignAdminRoles($tenant, $user);

            // Registrasi bersifat publik, jadi tidak ada pelaku terautentikasi.
            app(LogAuditAction::class)->handle('tenant.registered', $tenant, null, [
                'slug' => $slug,
                'email' => $user->email,
            ], 'Tenant baru '.$tenant->name.' terdaftar dengan admin '.$user->email.'.', $tenant);

            return ['tenant' => $tenant, 'user' => $user];
        });
    }

    /**
     * Tenant baru butuh role kliniknya sendiri sebelum admin pertama
     * bisa diberi peran; tanpa ini admin login tanpa akses modul apa pun.
     */
    private function assignAdminRoles(Tenant $tenant, User $user): void
    {
        app(SyncTenantClinicRolesAction::class)->handle($tenant->id);
        app(SeedDefaultCommissionRulesAction::class)->handle($tenant->id);

        // Satuan bawaan supaya produk pertama bisa langsung disimpan tanpa
        // mampir dulu ke menu Satuan.
        app(SeedDefaultUnitsAction::class)->handle($tenant->id);

        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId(SetPermissionTeamId::PLATFORM_TEAM_ID);
        $user->assignRole(UserRole::TenantAdmin->value);

        $registrar->setPermissionsTeamId($tenant->id);
        $user->assignRole(ClinicRole::Admin->value);
    }
}
