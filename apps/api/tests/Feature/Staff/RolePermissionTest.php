<?php

namespace Tests\Feature\Staff;

use App\Actions\Tenant\SyncTenantClinicRolesAction;
use App\Enums\ClinicRole;
use App\Http\Middleware\SetPermissionTeamId;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Izin peran menentukan siapa boleh membuka apa. Yang dijaga di sini: izin
 * benar-benar tersimpan dan berlaku, penyesuaian satu klinik tidak bocor ke
 * klinik lain, dan admin tidak bisa mengunci dirinya sendiri di luar.
 */
class RolePermissionTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    /**
     * Dibaca langsung dari tabel dengan kolom team disaring eksplisit —
     * `Role::where('name', ...)` tidak ikut menyaringnya.
     *
     * @return array<int, string>
     */
    private function permissionsOf(string $roleName, ?int $tenantId = null): array
    {
        return Role::query()
            ->where(config('permission.column_names.team_foreign_key'), $tenantId ?? $this->tenant->id)
            ->where('name', $roleName)
            ->where('guard_name', SyncTenantClinicRolesAction::GUARD)
            ->first()
            ?->permissions->pluck('name')->sort()->values()->all() ?? [];
    }

    /** Semua modul dimatikan kecuali yang disebut. */
    private function payload(array $enabled): array
    {
        $modules = [];

        foreach (SyncTenantClinicRolesAction::modules() as $module) {
            $modules[$module] = $enabled[$module] ?? ['view' => false, 'manage' => false];
        }

        return ['modules' => $modules];
    }

    public function test_matrix_lists_every_role_and_module(): void
    {
        $this->actingAsClinicUser();

        $response = $this->getJson($this->tenantUrl('roles'))->assertOk();

        $this->assertCount(4, $response->json('data.roles'));
        $this->assertCount(
            count(SyncTenantClinicRolesAction::modules()),
            $response->json('data.modules'),
        );

        // Bawaan admin: bisa mengelola staf.
        $this->assertTrue($response->json('data.matrix.admin.staff.manage'));
        // Bawaan kasir: tidak menyentuh staf sama sekali.
        $this->assertFalse($response->json('data.matrix.cashier.staff.view'));
    }

    public function test_admin_grants_a_module_to_another_role(): void
    {
        $this->actingAsClinicUser();

        $this->assertNotContains('expense.view', $this->permissionsOf('cashier'));

        $this->putJson(
            $this->tenantUrl('roles/cashier'),
            $this->payload(['expense' => ['view' => true, 'manage' => false]]),
        )
            ->assertOk()
            ->assertJsonPath('data.matrix.cashier.expense.view', true);

        $this->assertContains('expense.view', $this->permissionsOf('cashier'));
    }

    /** Yang berlaku adalah isi tabel, bukan matriks di kode. */
    public function test_a_cashier_can_reach_a_module_after_being_granted_it(): void
    {
        $this->actingAsClinicUser();

        $this->putJson(
            $this->tenantUrl('roles/cashier'),
            $this->payload(['expense' => ['view' => true, 'manage' => false]]),
        )->assertOk();

        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->getJson($this->tenantUrl('expenses'))->assertOk();
    }

    public function test_revoking_a_module_closes_it_again(): void
    {
        $this->actingAsClinicUser();

        // Dokter bawaannya boleh membaca layanan; dicabut.
        $this->putJson($this->tenantUrl('roles/doctor'), $this->payload([]))->assertOk();

        $this->actingAsClinicUser(ClinicRole::Doctor);

        $this->getJson($this->tenantUrl('services'))->assertForbidden();
    }

    /**
     * Mencabut izin pengurus dari peran sendiri berarti tidak ada lagi jalan
     * kembali — layarnya ikut tertutup bersama izinnya.
     */
    public function test_admin_cannot_strip_the_keys_from_its_own_role(): void
    {
        $this->actingAsClinicUser();

        $this->putJson(
            $this->tenantUrl('roles/admin'),
            $this->payload(['staff' => ['view' => true, 'manage' => true]]),
        )->assertStatus(422);

        $this->putJson(
            $this->tenantUrl('roles/admin'),
            $this->payload(['role' => ['view' => true, 'manage' => true]]),
        )->assertStatus(422);

        // Izin lamanya tetap utuh.
        $this->assertContains('role.manage', $this->permissionsOf('admin'));
        $this->assertContains('staff.manage', $this->permissionsOf('admin'));
    }

    public function test_admin_may_strip_those_keys_from_another_role(): void
    {
        $this->actingAsClinicUser();

        $this->putJson(
            $this->tenantUrl('roles/doctor'),
            $this->payload(['patient' => ['view' => true, 'manage' => false]]),
        )->assertOk();

        $this->assertSame(['patient.view'], $this->permissionsOf('doctor'));
    }

    public function test_manage_without_view_is_rejected(): void
    {
        $this->actingAsClinicUser();

        $this->putJson(
            $this->tenantUrl('roles/doctor'),
            $this->payload(['expense' => ['view' => false, 'manage' => true]]),
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors('modules.expense.manage');
    }

    public function test_unknown_module_is_rejected(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('roles/doctor'), [
            'modules' => ['modul_karangan' => ['view' => true, 'manage' => true]],
        ])->assertStatus(422);
    }

    public function test_unknown_role_is_rejected(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('roles/superadmin'), $this->payload([]))
            ->assertStatus(422);
    }

    public function test_reset_restores_the_default_template(): void
    {
        $this->actingAsClinicUser();

        $this->putJson(
            $this->tenantUrl('roles/doctor'),
            $this->payload(['expense' => ['view' => true, 'manage' => true]]),
        )->assertOk();

        $this->postJson($this->tenantUrl('roles/doctor/reset'))->assertOk();

        $expected = SyncTenantClinicRolesAction::permissionNamesFor(
            SyncTenantClinicRolesAction::MATRIX['doctor'],
        );

        $this->assertEqualsCanonicalizing($expected, $this->permissionsOf('doctor'));
    }

    public function test_a_doctor_cannot_change_permissions(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);

        $this->getJson($this->tenantUrl('roles'))->assertForbidden();
        $this->putJson($this->tenantUrl('roles/cashier'), $this->payload([]))->assertForbidden();
        $this->postJson($this->tenantUrl('roles/cashier/reset'))->assertForbidden();
    }

    /**
     * Izin disimpan per klinik. Kalau bocor, satu klinik yang melonggarkan
     * aksesnya akan melonggarkan akses semua klinik.
     */
    public function test_changing_permissions_does_not_touch_other_clinics(): void
    {
        $lain = $this->createTenant('klinik-tetangga');

        $this->tenant = $this->createTenant('klinik-kita');
        $this->actingAsClinicUser();

        $this->putJson(
            $this->tenantUrl('roles/cashier'),
            $this->payload(['expense' => ['view' => true, 'manage' => true]]),
        )->assertOk();

        $this->assertContains('expense.manage', $this->permissionsOf('cashier'));
        $this->assertNotContains('expense.manage', $this->permissionsOf('cashier', $lain->id));
    }

    /**
     * Bug yang jadi alasan pemisahan ensureRolesExist: menerima undangan
     * memanggil sinkronisasi penuh, dan seluruh penyesuaian izin klinik
     * kembali ke bawaan hanya karena ada staf baru bergabung.
     */
    public function test_accepting_an_invitation_keeps_customised_permissions(): void
    {
        $this->actingAsClinicUser();

        $this->putJson(
            $this->tenantUrl('roles/cashier'),
            $this->payload(['expense' => ['view' => true, 'manage' => false]]),
        )->assertOk();

        $invitation = Invitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'staf.baru@klinik-uji.test',
            'role' => 'member',
            'clinic_role' => ClinicRole::Cashier,
            'token' => 'token-uji-undangan',
            'expires_at' => now()->addDays(3),
        ]);

        $this->postJson('/api/invitations/'.$invitation->token.'/accept', [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSuccessful();

        // Penyesuaiannya harus bertahan.
        $this->assertContains('expense.view', $this->permissionsOf('cashier'));

        // Dan stafnya benar-benar mendapat perannya.
        $baru = User::query()->where('email', 'staf.baru@klinik-uji.test')->first();
        $this->assertNotNull($baru);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        $this->assertTrue($baru->hasRole(ClinicRole::Cashier->value));
        app(PermissionRegistrar::class)->setPermissionsTeamId(SetPermissionTeamId::PLATFORM_TEAM_ID);
    }

    public function test_changes_are_written_to_the_audit_log(): void
    {
        $this->actingAsClinicUser();

        $this->putJson(
            $this->tenantUrl('roles/cashier'),
            $this->payload(['expense' => ['view' => true, 'manage' => false]]),
        )->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'role.permissions_changed']);

        $this->postJson($this->tenantUrl('roles/cashier/reset'))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'role.permissions_reset']);
    }
}
