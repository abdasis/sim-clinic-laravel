<?php

namespace Tests\Feature\Chatbot;

use App\Actions\Chatbot\GetClinicInfoAction;
use App\Enums\ClinicRole;
use App\Models\CompanyProfileSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Tautan Google Maps klinik, dari formulir pengaturan sampai jawaban chatbot.
 *
 * Alamat tertulis saja menyisakan pekerjaan di sisi pasien: ia harus
 * mengetik ulang alamatnya ke aplikasi peta, dan nama komplek yang mirip
 * membuatnya berakhir di jalan yang salah.
 *
 * Disimpan per klinik, bukan sebagai konstanta — titik peta klinik lain
 * yang ikut terbagikan jauh lebih buruk daripada tidak ada peta sama sekali.
 */
class ClinicMapsLinkTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const MAPS = 'https://maps.app.goo.gl/vfu1wvd72ojx1PHE9';

    private function settingsUrl(): string
    {
        return $this->tenantUrl('company-profile/settings');
    }

    public function test_admin_saves_the_address_and_maps_link(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->settingsUrl(), [
            'address' => 'Komp. Pusat Bisnis Ringroad, Medan Selayang, Kota Medan.',
            'maps_url' => self::MAPS,
        ])->assertOk();

        $setting = CompanyProfileSetting::first();
        $this->assertSame(self::MAPS, $setting->maps_url);
    }

    public function test_the_saved_link_reaches_the_settings_screen(): void
    {
        $this->actingAsClinicUser();
        $this->putJson($this->settingsUrl(), ['maps_url' => self::MAPS])->assertOk();

        $this->getJson($this->settingsUrl())
            ->assertOk()
            ->assertJsonPath('data.maps_url', self::MAPS);
    }

    /** Yang dibaca chatbot saat pasien bertanya lokasi. */
    public function test_the_chatbot_answer_carries_the_maps_link(): void
    {
        $this->actingAsClinicUser();

        CompanyProfileSetting::create([
            'tenant_id' => $this->tenant->id,
            'address' => 'Komp. Pusat Bisnis Ringroad, Medan Selayang, Kota Medan.',
            'maps_url' => self::MAPS,
        ]);

        $info = app(GetClinicInfoAction::class)->handle();

        $this->assertSame(self::MAPS, $info['maps_url']);
        $this->assertSame(
            'Komp. Pusat Bisnis Ringroad, Medan Selayang, Kota Medan.',
            $info['address'],
        );
    }

    /** Klinik yang belum mengisinya menjawab alamat saja, bukan tautan kosong. */
    public function test_a_clinic_without_a_link_answers_with_the_address_only(): void
    {
        $this->actingAsClinicUser();

        CompanyProfileSetting::create([
            'tenant_id' => $this->tenant->id,
            'address' => 'Jl. Melati No. 1',
        ]);

        $info = app(GetClinicInfoAction::class)->handle();

        $this->assertNull($info['maps_url']);
        $this->assertSame('Jl. Melati No. 1', $info['address']);
    }

    /** Tautan yang tidak bisa dibuka ditolak sebagai galat formulir. */
    public function test_a_malformed_link_is_rejected(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->settingsUrl(), ['maps_url' => 'bukan-tautan'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('maps_url');
    }

    /** Titik peta klinik lain tidak boleh ikut terbaca. */
    public function test_the_link_is_scoped_to_its_own_clinic(): void
    {
        $this->actingAsClinicUser();
        $this->putJson($this->settingsUrl(), ['maps_url' => self::MAPS])->assertOk();

        $other = $this->createTenant('klinik-lain');
        $this->actingAsClinicUser(ClinicRole::Admin, $other);

        $this->assertNull(app(GetClinicInfoAction::class)->handle()['maps_url']);
    }
}
