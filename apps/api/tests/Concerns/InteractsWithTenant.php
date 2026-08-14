<?php

namespace Tests\Concerns;

use App\Actions\Tenant\SyncTenantClinicRolesAction;
use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * Perkakas untuk feature test yang menembak route tenant-scoped:
 * menyiapkan tenant, permission spatie, dan user dengan peran klinik.
 */
trait InteractsWithTenant
{
    protected Tenant $tenant;

    protected function createTenant(string $slug = 'klinik-uji'): Tenant
    {
        $tenant = Tenant::create([
            'name' => 'Klinik '.$slug,
            'slug' => $slug,
            'phone' => '08110000000',
            'status' => 'active',
        ]);

        // Permission global cukup sekali; role klinik dibuat per tenant.
        $this->seed(RolesAndPermissionsSeeder::class);
        app(SyncTenantClinicRolesAction::class)->handle($tenant->id);

        // Tiru ResolveTenant supaya BelongsToTenant mengisi tenant_id dan
        // TenantScope aktif, sama seperti saat request sungguhan.
        app()->instance('tenant', $tenant);

        return $tenant;
    }

    /**
     * Siapkan tenant default sekaligus user ber-peran klinik yang login.
     */
    protected function actingAsClinicUser(ClinicRole $role = ClinicRole::Admin, ?Tenant $tenant = null): User
    {
        $tenant ??= $this->tenant ??= $this->createTenant();

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Staf '.$role->value,
            'email' => $role->value.'@'.$tenant->slug.'.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => $role,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Prefix URL API untuk tenant yang sedang diuji.
     */
    protected function tenantUrl(string $path, ?Tenant $tenant = null): string
    {
        $tenant ??= $this->tenant;

        return '/api/'.$tenant->slug.'/clinic/'.ltrim($path, '/');
    }
}
