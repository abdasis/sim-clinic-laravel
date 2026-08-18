<?php

namespace Tests\Feature\Broadcast;

use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Yang diatur klinik dari WAHA hanya satu: nama sesinya. Nama itu menentukan
 * nomor WhatsApp mana yang dipakai mengirim, jadi salah isi berarti pesan
 * keluar dari nomor klinik lain.
 */
class WhatsappSessionTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_clinic_saves_its_own_session_name(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('broadcasts/settings'), ['session' => 'klinik-satu'])
            ->assertOk()
            ->assertJsonPath('data.session', 'klinik-satu');

        $this->getJson($this->tenantUrl('broadcasts/settings'))
            ->assertOk()
            ->assertJsonPath('data.session', 'klinik-satu')
            // Selama pengelola platform belum mengisi servernya, klinik tidak
            // bisa berbuat apa-apa dengan nama sesi ini.
            ->assertJsonPath('data.waha_available', false);

        WahaSetting::create(['base_url' => 'https://waha.test', 'api_key' => 'kunci']);

        $this->getJson($this->tenantUrl('broadcasts/settings'))
            ->assertJsonPath('data.waha_available', true);
    }

    public function test_session_name_rejects_characters_waha_does_not_accept(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('broadcasts/settings'), ['session' => 'Klinik Satu!'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('session');

        $this->assertNull(WhatsappSetting::query()->first());
    }

    /**
     * Dua klinik yang menunjuk sesi sama akan mengirim dari nomor yang sama —
     * dan pelanggaran unique-nya muncul sebagai 500, bukan pesan yang bisa
     * dibaca admin. Karena itu ditolak sejak validasi.
     */
    public function test_session_already_used_by_another_clinic_is_rejected(): void
    {
        $other = $this->createTenant('klinik-tetangga');
        WhatsappSetting::create(['tenant_id' => $other->id, 'session' => 'sesi-bersama']);

        $this->tenant = $this->createTenant('klinik-kita');
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('broadcasts/settings'), ['session' => 'sesi-bersama'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('session');
    }

    public function test_clinic_can_keep_its_own_session_when_saving_again(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('broadcasts/settings'), ['session' => 'klinik-satu'])
            ->assertOk();

        // Menyimpan nilai yang sama tidak boleh dianggap bentrok dengan dirinya sendiri.
        $this->putJson($this->tenantUrl('broadcasts/settings'), ['session' => 'klinik-satu'])
            ->assertOk()
            ->assertJsonPath('data.session', 'klinik-satu');
    }

    public function test_empty_session_turns_automatic_sending_off(): void
    {
        $this->actingAsClinicUser();

        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-satu']);
        WahaSetting::create(['base_url' => 'https://waha.test', 'api_key' => 'kunci']);

        $this->putJson($this->tenantUrl('broadcasts/settings'), ['session' => null])
            ->assertOk()
            ->assertJsonPath('data.session', null);

        // Tanpa sesi tidak ada yang bisa dikirimi, dan itu ditolak dengan pesan
        // yang jelas alih-alih gagal diam-diam di worker.
        $this->postJson($this->tenantUrl('broadcasts/test-message'), [
            'phone' => '081234567890',
            'message' => 'Halo',
        ])->assertStatus(422);
    }
}
