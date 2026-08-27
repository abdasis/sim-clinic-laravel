<?php

namespace Tests\Feature\Staff;

use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Admin satu klinik tidak boleh menyentuh staf klinik lain.
 *
 * Model User tidak memakai TenantScope — ia juga menampung admin platform
 * yang memang tidak bertenant — sehingga route model binding bawaan
 * menemukan pengguna klinik mana pun hanya dari id-nya. Sementara itu policy
 * staf hanya menanyakan wewenang pelakunya ("apakah dia admin klinik?"),
 * bukan apakah sasarannya sekliniknya.
 *
 * Gabungan keduanya membuat seluruh endpoint di berkas ini bisa ditembus
 * lintas klinik hanya dengan menebak id. Ditutup di pengikatan rutenya,
 * dan dikunci di sini supaya tidak terbuka lagi lewat endpoint kedelapan.
 */
class CrossTenantStaffAccessTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private User $foreign;

    private Tenant $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();

        $this->other = $this->createTenant('klinik-lain');
        $this->foreign = User::factory()->create([
            'tenant_id' => $this->other->id,
            'name' => 'Staf Tetangga',
            'email' => 'tetangga@meba.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Therapist,
        ]);

        // Kembali jadi klinik sendiri: createTenant mengikat tetangga.
        app()->instance('tenant', $this->tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    }

    public function test_editing_a_foreign_staff_is_refused(): void
    {
        $this->putJson($this->tenantUrl("staff/{$this->foreign->id}"), [
            'name' => 'Diubah Diam-diam',
            'email' => 'tetangga@meba.test',
            'clinic_role' => 'therapist',
        ])->assertNotFound();

        $this->assertSame('Staf Tetangga', $this->foreign->fresh()->name);
    }

    public function test_deleting_a_foreign_staff_is_refused(): void
    {
        $this->deleteJson($this->tenantUrl("staff/{$this->foreign->id}"))
            ->assertNotFound();

        $this->assertNotNull($this->foreign->fresh());
    }

    public function test_changing_a_foreign_staffs_role_is_refused(): void
    {
        $this->patchJson($this->tenantUrl("staff/{$this->foreign->id}/role"), [
            'clinic_role' => 'admin',
        ])->assertNotFound();

        $this->assertSame(ClinicRole::Therapist, $this->foreign->fresh()->clinic_role);
    }

    public function test_deactivating_a_foreign_staff_is_refused(): void
    {
        $this->postJson($this->tenantUrl("staff/{$this->foreign->id}/deactivate"))
            ->assertNotFound();

        $this->assertSame(UserStatus::Active, $this->foreign->fresh()->status);
    }

    public function test_resetting_a_foreign_staffs_password_is_refused(): void
    {
        $this->putJson($this->tenantUrl("staff/{$this->foreign->id}/password"), [
            'password' => 'kata-sandi-baru-9',
            'password_confirmation' => 'kata-sandi-baru-9',
        ])->assertNotFound();

        $this->assertTrue(Hash::check('password123', $this->foreign->fresh()->password));
    }

    /** Jalur manajemen anggota tenant punya lubang yang sama. */
    public function test_removing_a_foreign_member_is_refused(): void
    {
        $this->postJson('/api/'.$this->tenant->slug."/users/{$this->foreign->id}/remove")
            ->assertNotFound();

        $this->assertNotNull($this->foreign->fresh());
    }

    public function test_changing_a_foreign_members_platform_role_is_refused(): void
    {
        $this->patchJson('/api/'.$this->tenant->slug."/users/{$this->foreign->id}/role", [
            'role' => 'tenant_admin',
        ])->assertNotFound();

        $this->assertSame(UserRole::Member, $this->foreign->fresh()->role);
    }

    /** Staf sendiri tetap bisa dikelola seperti biasa. */
    public function test_own_staff_are_still_reachable(): void
    {
        $mine = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Jasmin',
            'email' => 'jasmin@meba.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Therapist,
        ]);

        $this->putJson($this->tenantUrl("staff/{$mine->id}"), [
            'name' => 'Jasmin Sari',
            'email' => 'jasmin@meba.test',
            'clinic_role' => 'therapist',
        ])->assertOk();

        $this->assertSame('Jasmin Sari', $mine->fresh()->name);
    }
}
