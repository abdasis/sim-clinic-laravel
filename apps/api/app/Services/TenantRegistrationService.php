<?php

namespace App\Services;

use App\Actions\LogAuditAction;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Registrasi tenant baru + admin pertama secara atomik (spec 001, US1).
 */
class TenantRegistrationService
{
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
            if (Tenant::where('slug', $slug)->exists()) {
                abort(422, __('tenant.slug_taken'));
            }

            $tenant = Tenant::create([
                'name' => $data['company_name'],
                'slug' => $slug,
                'phone' => $data['phone'],
                'status' => TenantStatus::Active,
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['company_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => UserRole::TenantAdmin,
                'status' => UserStatus::Active,
            ]);

            app(LogAuditAction::class)->handle('tenant.registered', $tenant, $user, [
                'slug' => $slug,
                'email' => $user->email,
            ], $tenant);

            return ['tenant' => $tenant, 'user' => $user];
        });
    }
}
