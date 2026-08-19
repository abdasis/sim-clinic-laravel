<?php

namespace Tests\Feature\Category;

use App\Actions\Tenant\SyncTenantClinicRolesAction;
use App\Enums\ClinicRole;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Izin modul dibuat oleh seeder, sementara deploy hanya menjalankan migrate.
 * Modul yang lahir setelah seeder terakhir dijalankan — `category` — izinnya
 * tidak pernah sampai ke server, sehingga halaman kelola kategori ditolak 403
 * bahkan untuk admin klinik dan tampil sebagai daftar yang memuat terus.
 */
class CategoryPermissionBackfillTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    /** Kembalikan database ke keadaan sebelum modul kategori ada. */
    private function forgetCategoryModule(): void
    {
        DB::table('role_has_permissions')
            ->whereIn('permission_id', DB::table('permissions')->where('name', 'like', 'category.%')->pluck('id'))
            ->delete();

        DB::table('permissions')->where('name', 'like', 'category.%')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function runBackfill(): void
    {
        (require database_path('migrations/2026_08_19_000000_grant_missing_clinic_permissions.php'))->up();
    }

    public function test_admin_is_locked_out_before_the_backfill(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->forgetCategoryModule();

        // Inilah yang terjadi di server: 403, yang di layar terlihat sebagai
        // daftar yang tidak pernah selesai memuat.
        $this->getJson($this->tenantUrl('categories?filter[type]=service&filter[status]=all'))
            ->assertForbidden();
    }

    public function test_backfill_restores_access_for_the_clinic_admin(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Skinbooster',
            'type' => 'service',
            'status' => 'active',
        ]);

        $this->forgetCategoryModule();
        $this->runBackfill();

        $this->getJson($this->tenantUrl('categories?filter[type]=service&filter[status]=all'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Skinbooster');
    }

    public function test_backfill_keeps_permissions_the_clinic_added_itself(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->forgetCategoryModule();

        // Klinik boleh menyesuaikan izin perannya sendiri; penyesuaian itu
        // tidak boleh hilang hanya karena modul baru dilengkapi.
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->tenant->id);

        Permission::findOrCreate('report.manage', SyncTenantClinicRolesAction::GUARD);
        Role::where('name', ClinicRole::Cashier->value)
            ->where('guard_name', SyncTenantClinicRolesAction::GUARD)
            ->first()
            ->givePermissionTo('report.manage');

        $this->runBackfill();

        $registrar->setPermissionsTeamId($this->tenant->id);
        $cashier = Role::where('name', ClinicRole::Cashier->value)
            ->where('guard_name', SyncTenantClinicRolesAction::GUARD)
            ->first();

        $this->assertTrue($cashier->hasPermissionTo('report.manage'));
    }

    public function test_backfill_does_not_hand_category_access_to_the_cashier(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->forgetCategoryModule();
        $this->runBackfill();

        $this->actingAsClinicUser(ClinicRole::Cashier);

        // Matriks tidak memberi kasir akses kategori; backfill hanya melengkapi
        // yang seharusnya ada, bukan membuka pintu baru.
        $this->getJson($this->tenantUrl('categories?filter[type]=service&filter[status]=all'))
            ->assertForbidden();
    }
}
