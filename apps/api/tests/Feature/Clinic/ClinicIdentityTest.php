<?php

namespace Tests\Feature\Clinic;

use App\Enums\ClinicRole;
use App\Models\CompanyProfileSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Identitas klinik yang menempel di sidebar dan kop nota.
 *
 * Yang dijaga di sini: semua peran boleh membacanya — kalau tidak, sidebar
 * dokter dan kasir kehilangan nama serta logo kliniknya — dan klinik yang
 * belum pernah mengisi profil perusahaan tetap mendapat nama dan telepon
 * dari data tenantnya sendiri, bukan bidang kosong.
 */
class ClinicIdentityTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_identity_falls_back_to_tenant_data_without_a_profile(): void
    {
        $this->actingAsClinicUser();

        $this->getJson($this->tenantUrl('identity'))
            ->assertOk()
            ->assertJsonPath('data.slug', $this->tenant->slug)
            ->assertJsonPath('data.name', $this->tenant->name)
            ->assertJsonPath('data.phone', $this->tenant->phone)
            ->assertJsonPath('data.logo_url', null);
    }

    /** Nama di profil perusahaan menang atas nama tenant. */
    public function test_profile_site_name_overrides_the_tenant_name(): void
    {
        $this->actingAsClinicUser();

        CompanyProfileSetting::create([
            'tenant_id' => $this->tenant->id,
            // site_name dwibahasa (kolom json); tagline dan alamat kolom teks biasa.
            'site_name' => ['id' => 'Meba Clinic Aesthetic'],
            'tagline' => 'Kulit sehat, percaya diri',
            'address' => 'Jl. Ringroad No. 1, Medan',
            'receipt_note' => 'Terima kasih atas kunjungan Anda.',
        ]);

        $this->getJson($this->tenantUrl('identity'))
            ->assertOk()
            ->assertJsonPath('data.name', 'Meba Clinic Aesthetic')
            ->assertJsonPath('data.tagline', 'Kulit sehat, percaya diri')
            ->assertJsonPath('data.address', 'Jl. Ringroad No. 1, Medan')
            // Kop nota membacanya dari sini, jadi harus ikut terkirim.
            ->assertJsonPath('data.receipt_note', 'Terima kasih atas kunjungan Anda.');
    }

    /**
     * Sengaja tidak dijaga izin modul: siapa pun yang sudah lolos ke dalam
     * klinik berhak tahu klinik mana yang sedang dibukanya.
     */
    public function test_every_clinic_role_can_read_the_identity(): void
    {
        foreach (ClinicRole::cases() as $role) {
            $this->actingAsClinicUser($role);

            $this->getJson($this->tenantUrl('identity'))
                ->assertOk()
                ->assertJsonPath('data.slug', $this->tenant->slug);
        }
    }

    public function test_identity_requires_authentication(): void
    {
        $this->tenant = $this->createTenant();

        $this->getJson($this->tenantUrl('identity'))->assertUnauthorized();
    }

    /** Yang terbaca selalu klinik di URL, bukan klinik lain. */
    public function test_identity_is_scoped_to_the_tenant_in_the_url(): void
    {
        $this->actingAsClinicUser();
        $other = $this->createTenant('klinik-lain');
        $this->actingAsClinicUser(ClinicRole::Admin, $other);

        $this->getJson($this->tenantUrl('identity', $other))
            ->assertOk()
            ->assertJsonPath('data.slug', $other->slug)
            ->assertJsonPath('data.name', $other->name);
    }
}
