<?php

namespace Tests\Feature\Broadcast;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\WahaSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Server WAHA dipakai bersama seluruh klinik: satu API key membuka WhatsApp
 * semuanya. Karena itu hanya pengelola platform yang boleh menyentuhnya, dan
 * kuncinya tidak boleh bocor lewat response maupun dump basis data.
 */
class CentralWahaSettingTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_platform_admin_saves_server_and_key_is_never_echoed_back(): void
    {
        $this->tenant = $this->createTenant();
        Sanctum::actingAs($this->platformAdmin());

        $this->putJson('/api/central/waha', [
            'base_url' => 'https://waha.klinik.test',
            'api_key' => 'kunci-rahasia',
        ])
            ->assertOk()
            ->assertJsonPath('data.base_url', 'https://waha.klinik.test')
            ->assertJsonPath('data.has_api_key', true)
            ->assertJsonMissingPath('data.api_key');

        $this->getJson('/api/central/waha')
            ->assertOk()
            ->assertJsonPath('data.base_url', 'https://waha.klinik.test')
            ->assertJsonPath('data.has_api_key', true)
            ->assertJsonMissingPath('data.api_key');

        $this->assertSame('kunci-rahasia', WahaSetting::query()->first()->api_key);
    }

    public function test_key_is_stored_encrypted_not_as_plain_text(): void
    {
        $this->tenant = $this->createTenant();
        Sanctum::actingAs($this->platformAdmin());

        $this->putJson('/api/central/waha', [
            'base_url' => 'https://waha.klinik.test',
            'api_key' => 'kunci-rahasia',
        ])->assertOk();

        $stored = DB::table('waha_settings')->value('api_key');

        $this->assertNotSame('kunci-rahasia', $stored);
        $this->assertStringNotContainsString('kunci-rahasia', (string) $stored);
    }

    /**
     * Menyimpan ulang alamatnya saja tidak boleh diam-diam mencabut kredensial
     * — kuncinya tidak pernah dikirim balik, jadi field kosong berarti "biarkan".
     */
    public function test_blank_key_keeps_the_stored_one(): void
    {
        $this->tenant = $this->createTenant();
        Sanctum::actingAs($this->platformAdmin());

        WahaSetting::create(['base_url' => 'https://lama.test', 'api_key' => 'kunci-lama']);

        $this->putJson('/api/central/waha', [
            'base_url' => 'https://baru.test',
            'api_key' => '',
        ])->assertOk();

        $setting = WahaSetting::query()->first();

        $this->assertSame('https://baru.test', $setting->base_url);
        $this->assertSame('kunci-lama', $setting->api_key);
    }

    public function test_clinic_user_cannot_read_or_change_the_shared_server(): void
    {
        $this->actingAsClinicUser();

        $this->getJson('/api/central/waha')->assertForbidden();

        $this->putJson('/api/central/waha', [
            'base_url' => 'https://milik-sendiri.test',
            'api_key' => 'kunci',
        ])->assertForbidden();

        $this->assertNull(WahaSetting::query()->first());
    }

    public function test_base_url_must_be_a_url(): void
    {
        $this->tenant = $this->createTenant();
        Sanctum::actingAs($this->platformAdmin());

        $this->putJson('/api/central/waha', ['base_url' => 'bukan-alamat'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('base_url');
    }

    private function platformAdmin(): User
    {
        return User::factory()->create([
            'name' => 'Admin Platform',
            'email' => 'platform@sim-clinic.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::PlatformAdmin,
            'status' => UserStatus::Active,
        ]);
    }
}
