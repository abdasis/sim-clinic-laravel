<?php

namespace Tests\Feature\Whatsapp;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Setelan gateway WhatsApp per klinik disimpan pengelola platform dari
 * dashboard central. Yang paling penting: nilai rahasia tidak boleh keluar
 * dari server dan API key kosong tidak boleh menghapus kredensial lama.
 */
class CentralGatewayClinicTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

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

    private function otherTenant(string $name, string $slug): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'phone' => '08110000000',
            'status' => 'active',
        ]);
    }

    public function test_clinic_admin_cannot_read_or_change_clinic_gateway_settings(): void
    {
        $this->actingAsClinicUser();

        $this->getJson('/api/central/whatsapp-gateway/clinics')->assertForbidden();
        $this->putJson('/api/central/whatsapp-gateway/clinics/'.$this->tenant->id, ['session' => 'sesi-baru'])
            ->assertForbidden();
        $this->postJson('/api/central/whatsapp-gateway/clinics/'.$this->tenant->id.'/webhook')
            ->assertForbidden();

        $this->assertSame(0, WhatsappSetting::withoutGlobalScopes()->count());
    }

    public function test_index_lists_clinics_with_status_and_hides_secrets(): void
    {
        $tenantA = $this->otherTenant('Klinik A', 'klinik-a');
        $this->otherTenant('Klinik B', 'klinik-b');
        $this->otherTenant('Central', 'central');

        WhatsappSetting::create([
            'tenant_id' => $tenantA->id,
            'session' => 'sesi-a',
            'daily_message_limit' => 100,
            'gateway_api_key' => 'api-rahasia-a',
            'gateway_webhook_token' => 'token-publik-a',
            'gateway_webhook_secret' => 'secret-rahasia-a',
            'gateway_password' => 'sandi-rahasia-a',
        ]);

        Sanctum::actingAs($this->platformAdmin());
        config(['app.url' => 'https://api.contoh.test']);

        $response = $this->getJson('/api/central/whatsapp-gateway/clinics')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'klinik-a')
            ->assertJsonPath('data.0.session', 'sesi-a')
            ->assertJsonPath('data.0.api_key_configured', true)
            ->assertJsonPath('data.0.webhook_configured', true)
            ->assertJsonPath('data.0.webhook_token_set', true)
            ->assertJsonPath('data.0.webhook_url', 'https://api.contoh.test/api/whatsapp/webhook/token-publik-a')
            ->assertJsonPath('data.1.slug', 'klinik-b')
            ->assertJsonPath('data.1.api_key_configured', false)
            ->assertJsonPath('data.1.webhook_configured', false)
            ->assertJsonPath('data.1.webhook_url', null);

        // Token memang bagian dari URL webhook, jadi boleh tampil. Rahasia,
        // API key, dan kata sandi tidak.
        $body = $response->getContent();
        $this->assertStringNotContainsString('api-rahasia-a', $body);
        $this->assertStringNotContainsString('secret-rahasia-a', $body);
        $this->assertStringNotContainsString('sandi-rahasia-a', $body);
    }

    public function test_update_stores_api_key_encrypted_and_clears_old_password(): void
    {
        $this->tenant = $this->createTenant();
        $setting = WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'gateway_api_key' => 'api-lama-rahasia',
            'gateway_password' => 'sandi-lama',
            'gateway_webhook_token' => 'token-lama',
            'gateway_webhook_secret' => 'secret-lama',
        ]);

        Sanctum::actingAs($this->platformAdmin());
        config(['app.url' => 'https://api.contoh.test']);

        $this->putJson('/api/central/whatsapp-gateway/clinics/'.$this->tenant->id, [
            'api_key' => 'api-baru-rahasia',
            'session' => 'sesi-baru',
            'daily_message_limit' => 250,
        ])
            ->assertOk()
            ->assertJsonPath('data.session', 'sesi-baru')
            ->assertJsonPath('data.daily_message_limit', 250)
            ->assertJsonPath('data.api_key_configured', true)
            ->assertJsonPath('data.webhook_url', 'https://api.contoh.test/api/whatsapp/webhook/token-lama');

        $raw = DB::table('whatsapp_settings')->where('tenant_id', $this->tenant->id)->first();
        $this->assertNotSame('api-baru-rahasia', $raw->gateway_api_key);

        $fresh = $setting->fresh();
        $this->assertSame('api-baru-rahasia', $fresh->gateway_api_key);
        $this->assertNull($fresh->gateway_password);
        $this->assertSame('sesi-baru', $fresh->session);
        $this->assertSame(250, (int) $fresh->daily_message_limit);

        // Webhook yang sudah terdaftar tidak ikut dihapus.
        $this->assertSame('secret-lama', $fresh->gateway_webhook_secret);
        $this->assertSame('token-lama', $fresh->gateway_webhook_token);
    }

    public function test_blank_api_key_keeps_the_existing_credential(): void
    {
        $this->tenant = $this->createTenant();
        $setting = WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'gateway_api_key' => 'api-lama-rahasia',
        ]);

        Sanctum::actingAs($this->platformAdmin());

        $this->putJson('/api/central/whatsapp-gateway/clinics/'.$this->tenant->id, [
            'api_key' => null,
            'session' => 'sesi-lain',
        ])->assertOk();

        $fresh = $setting->fresh();
        $this->assertSame('api-lama-rahasia', $fresh->gateway_api_key);
        $this->assertSame('sesi-lain', $fresh->session);
    }

    public function test_update_is_audited_without_secret_values(): void
    {
        $this->tenant = $this->createTenant();
        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'gateway_api_key' => 'api-lama-rahasia',
        ]);

        Sanctum::actingAs($this->platformAdmin());

        $this->putJson('/api/central/whatsapp-gateway/clinics/'.$this->tenant->id, [
            'api_key' => 'api-baru-rahasia',
            'session' => 'sesi-baru',
            'daily_message_limit' => 300,
        ])->assertOk();

        $activity = Activity::query()->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('API key diganti', (string) $activity->description);
        $this->assertStringContainsString('sesi diubah', (string) $activity->description);
        $this->assertStringContainsString('batas harian diubah', (string) $activity->description);
        $this->assertSame(
            ['gateway_api_key', 'session', 'daily_message_limit'],
            $activity->properties['changed'],
        );

        $properties = (string) json_encode($activity->properties);
        $this->assertStringNotContainsString('api-baru-rahasia', $properties);
        $this->assertStringNotContainsString('api-lama-rahasia', $properties);
    }

    public function test_session_and_daily_limit_are_validated(): void
    {
        $this->tenant = $this->createTenant();
        Sanctum::actingAs($this->platformAdmin());

        $this->putJson('/api/central/whatsapp-gateway/clinics/'.$this->tenant->id, [
            'session' => 'Sesi Tidak Valid',
            'daily_message_limit' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['session', 'daily_message_limit']);

        $this->putJson('/api/central/whatsapp-gateway/clinics/'.$this->tenant->id, [
            'session' => 'ab',
            'daily_message_limit' => 10001,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['session', 'daily_message_limit']);
    }

    public function test_webhook_requires_an_api_key(): void
    {
        $this->tenant = $this->createTenant();
        Sanctum::actingAs($this->platformAdmin());

        $this->postJson('/api/central/whatsapp-gateway/clinics/'.$this->tenant->id.'/webhook')
            ->assertStatus(422)
            ->assertJsonPath('message', __('whatsapp_gateway.api_key_required'));

        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
        ]);

        $this->postJson('/api/central/whatsapp-gateway/clinics/'.$this->tenant->id.'/webhook')
            ->assertStatus(422)
            ->assertJsonPath('message', __('whatsapp_gateway.api_key_required'));
    }

    public function test_webhook_refuses_a_local_app_url(): void
    {
        $this->tenant = $this->createTenant();
        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'gateway_api_key' => 'api-rahasia',
        ]);

        config(['app.url' => 'http://localhost:8123']);

        Sanctum::actingAs($this->platformAdmin());

        $this->postJson('/api/central/whatsapp-gateway/clinics/'.$this->tenant->id.'/webhook')
            ->assertStatus(422)
            ->assertJsonPath('message', __('whatsapp_gateway.public_url_required'));
    }

    public function test_webhook_is_registered_for_a_public_app_url(): void
    {
        $this->tenant = $this->createTenant();
        $setting = WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'gateway_api_key' => 'api-rahasia',
        ]);

        config([
            'app.url' => 'https://klinik.contoh.com',
            'whatsapp_gateway.base_url' => 'https://gateway.test',
        ]);

        Http::preventStrayRequests();
        Http::fake(['gateway.test/api/v1/webhooks' => Http::response([
            'success' => true,
            'data' => ['id' => 'w1', 'secret' => 'secret-baru'],
        ], 201)]);

        Sanctum::actingAs($this->platformAdmin());

        $response = $this->postJson('/api/central/whatsapp-gateway/clinics/'.$this->tenant->id.'/webhook')
            ->assertOk()
            ->assertJsonPath('meta.message', __('whatsapp_gateway.webhook_registered'))
            ->assertJsonPath('data.webhook_configured', true)
            ->assertJsonPath('data.webhook_token_set', true);

        $fresh = $setting->fresh();
        $this->assertSame('secret-baru', $fresh->gateway_webhook_secret);
        $this->assertNotEmpty($fresh->gateway_webhook_token);

        $response->assertJsonPath(
            'data.webhook_url',
            'https://klinik.contoh.com/api/whatsapp/webhook/'.$fresh->gateway_webhook_token,
        );

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/webhooks'
            && $request->hasHeader('X-API-Key', 'api-rahasia')
            && str_contains((string) $request['url'], '/api/whatsapp/webhook/'.$fresh->gateway_webhook_token));
    }
}
