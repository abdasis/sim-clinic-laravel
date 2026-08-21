<?php

namespace Tests\Feature\Staff;

use App\Enums\ClinicRole;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Mengundang staf baru ke klinik.
 *
 * Undangan adalah satu-satunya pintu masuk anggota baru, jadi yang dijaga di
 * sini: hanya pemegang `staff.manage` yang boleh membukanya, alamat yang
 * sudah jadi anggota atau masih punya undangan berjalan ditolak, dan
 * undangannya menempel di klinik yang mengundang.
 */
class UserInviteTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function inviteUrl(?Tenant $tenant = null): string
    {
        $tenant ??= $this->tenant;

        return '/api/'.$tenant->slug.'/users/invite';
    }

    public function test_admin_invites_a_new_member(): void
    {
        $this->actingAsClinicUser();

        $response = $this->postJson($this->inviteUrl(), [
            'email' => 'terapis.baru@meba.test',
            'role' => 'member',
            'clinic_role' => 'therapist',
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->json('data.token'));

        $invitation = Invitation::where('email', 'terapis.baru@meba.test')->first();
        $this->assertNotNull($invitation);
        $this->assertSame($this->tenant->id, $invitation->tenant_id);
        $this->assertSame(InvitationStatus::Pending, $invitation->status);
        $this->assertSame(ClinicRole::Therapist, $invitation->clinic_role);
        $this->assertTrue($invitation->expires_at->isFuture());
    }

    /** Peran klinik boleh dikosongkan — diisi belakangan saat undangan diterima. */
    public function test_clinic_role_is_optional(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->inviteUrl(), [
            'email' => 'belum.ditentukan@meba.test',
            'role' => 'member',
        ])->assertCreated();

        $this->assertNull(Invitation::first()->clinic_role);
    }

    public function test_an_existing_member_cannot_be_invited_again(): void
    {
        $admin = $this->actingAsClinicUser();

        $this->postJson($this->inviteUrl(), [
            'email' => $admin->email,
            'role' => 'member',
        ])->assertStatus(422);

        $this->assertSame(0, Invitation::query()->count());
    }

    /** Undangan berjalan tidak boleh digandakan; tokennya jadi ambigu. */
    public function test_a_pending_invitation_blocks_a_second_one(): void
    {
        $this->actingAsClinicUser();
        $payload = ['email' => 'calon@meba.test', 'role' => 'member'];

        $this->postJson($this->inviteUrl(), $payload)->assertCreated();
        $this->postJson($this->inviteUrl(), $payload)->assertStatus(422);

        $this->assertSame(1, Invitation::query()->count());
    }

    public function test_invalid_payload_is_rejected(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->inviteUrl(), [
            'email' => 'bukan-email',
            'role' => 'member',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        // Hanya dua peran platform yang boleh diundang; `platform_admin`
        // dibuat lewat jalur lain, bukan dari dalam klinik.
        $this->postJson($this->inviteUrl(), [
            'email' => 'calon@meba.test',
            'role' => 'platform_admin',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    /** Hanya pemegang staff.manage; dokter, terapis, dan kasir tidak. */
    public function test_non_admin_clinic_roles_cannot_invite(): void
    {
        foreach ([ClinicRole::Doctor, ClinicRole::Therapist, ClinicRole::Cashier] as $role) {
            $this->actingAsClinicUser($role);

            $this->postJson($this->inviteUrl(), [
                'email' => 'calon.'.$role->value.'@meba.test',
                'role' => 'member',
            ])->assertForbidden();
        }

        $this->assertSame(0, Invitation::query()->count());
    }

    public function test_inviting_requires_authentication(): void
    {
        $this->tenant = $this->createTenant();

        $this->postJson($this->inviteUrl(), [
            'email' => 'calon@meba.test',
            'role' => 'member',
        ])->assertUnauthorized();
    }

    /**
     * Alamat yang sama boleh diundang dua klinik berbeda — satu orang bisa
     * bekerja di lebih dari satu cabang.
     */
    public function test_the_same_email_may_be_invited_by_another_clinic(): void
    {
        $this->actingAsClinicUser();
        $this->postJson($this->inviteUrl(), [
            'email' => 'calon@meba.test',
            'role' => 'member',
        ])->assertCreated();

        $other = $this->createTenant('klinik-lain');
        $this->actingAsClinicUser(ClinicRole::Admin, $other);

        $this->postJson($this->inviteUrl($other), [
            'email' => 'calon@meba.test',
            'role' => 'member',
        ])->assertCreated();

        $this->assertSame(2, Invitation::query()->count());
    }
}
