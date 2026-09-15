<?php

namespace Tests\Feature\Membership;

use App\Enums\ClinicRole;
use App\Models\MembershipTier;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Master tingkat member — label klasifikasi pasien, tanpa potongan harga.
 *
 * Manfaat keanggotaan berjalan lewat poin loyalitas (lihat LoyaltyPointsTest);
 * tingkat di sini murni menandai siapa Gold, siapa Silver.
 */
class MembershipTierApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Gold',
            ...$overrides,
        ];
    }

    public function test_an_admin_can_create_a_tier(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('membership-tiers'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Gold')
            ->assertJsonPath('data.status', 'active');
    }

    /** Nama kembar bikin ragu mana yang dimaksud saat menandai pasien. */
    public function test_a_duplicate_name_is_rejected(): void
    {
        $this->actingAsClinicUser();
        $this->postJson($this->tenantUrl('membership-tiers'), $this->payload())->assertCreated();

        $this->postJson($this->tenantUrl('membership-tiers'), $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /**
     * Tingkat yang masih dipegang pasien tidak boleh lenyap.
     *
     * Menghapusnya berarti klasifikasi yang sedang berjalan hilang tanpa ada
     * yang memutuskan, dan pasien baru tahu saat labelnya lenyap di layar.
     */
    public function test_a_tier_still_held_by_patients_cannot_be_deleted(): void
    {
        $this->actingAsClinicUser();

        $tier = MembershipTier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gold',
        ]);

        Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'membership_tier_id' => $tier->id,
        ]);

        $this->deleteJson($this->tenantUrl("membership-tiers/{$tier->id}"))
            ->assertStatus(422);

        $this->assertDatabaseHas('membership_tiers', ['id' => $tier->id]);
    }

    public function test_an_unused_tier_can_be_deleted(): void
    {
        $this->actingAsClinicUser();

        $id = $this->postJson($this->tenantUrl('membership-tiers'), $this->payload())
            ->assertCreated()->json('data.id');

        $this->deleteJson($this->tenantUrl("membership-tiers/{$id}"))->assertOk();

        $this->assertDatabaseMissing('membership_tiers', ['id' => $id]);
    }

    /** Kasir perlu menelusuri tingkat pasien, bukan mengubahnya. */
    public function test_a_cashier_may_read_but_not_change_tiers(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->getJson($this->tenantUrl('membership-tiers'))->assertOk();
        $this->postJson($this->tenantUrl('membership-tiers'), $this->payload())->assertForbidden();
    }

    /** Terapis tidak berurusan dengan keanggotaan sama sekali. */
    public function test_a_therapist_cannot_see_tiers(): void
    {
        $this->actingAsClinicUser(ClinicRole::Therapist);

        $this->getJson($this->tenantUrl('membership-tiers'))->assertForbidden();
    }

    /** Tingkat klinik lain tidak boleh tersentuh, bahkan lewat id yang ditebak. */
    public function test_a_tier_from_another_clinic_is_not_found(): void
    {
        $this->actingAsClinicUser();

        $tier = MembershipTier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gold',
        ]);

        $other = $this->createTenant('klinik-lain');
        $this->actingAsClinicUser(ClinicRole::Admin, $other);

        $this->putJson($this->tenantUrl("membership-tiers/{$tier->id}", $other), $this->payload())
            ->assertNotFound();
        $this->deleteJson($this->tenantUrl("membership-tiers/{$tier->id}", $other))
            ->assertNotFound();
    }

    /** Pasien membawa keanggotaannya sendiri di daftar. */
    public function test_a_patient_carries_its_membership(): void
    {
        $this->actingAsClinicUser();

        $tier = MembershipTier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gold',
        ]);

        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'membership_tier_id' => $tier->id,
            'member_until' => now()->addYear(),
        ]);

        $this->getJson($this->tenantUrl('patients'))
            ->assertOk()
            ->assertJsonPath('data.0.membership_tier_name', 'Gold')
            ->assertJsonPath('data.0.membership.name', 'Gold')
            ->assertJsonPath('data.0.member_until', now()->addYear()->format('Y-m-d'));

        $this->assertNotNull($patient->fresh()->activeMembership());
    }

    /** Keanggotaan yang sudah lewat tidak boleh terlihat sama dengan yang hidup. */
    public function test_an_expired_membership_is_reported_as_none(): void
    {
        $this->actingAsClinicUser();

        $tier = MembershipTier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gold',
        ]);

        Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'membership_tier_id' => $tier->id,
            'member_until' => now()->subDay(),
        ]);

        $this->getJson($this->tenantUrl('patients'))
            ->assertOk()
            ->assertJsonPath('data.0.membership', null)
            ->assertJsonPath('data.0.membership_tier_name', 'Gold');
    }
}
