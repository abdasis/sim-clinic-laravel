<?php

namespace Tests\Feature\Whatsapp;

use App\Models\WhatsappSetting;
use App\Services\WhatsappGatewayProvisioningService;
use App\Support\WhatsappGatewayException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Provisioning akun gateway untuk tiap klinik: daftar sekali, buat API key,
 * lalu simpan kredensial. Percobaan ulang harus melanjutkan langkah yang
 * terputus, bukan mendaftarkan tenant kedua.
 */
class GatewayProvisioningTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        config(['whatsapp_gateway.base_url' => 'https://gateway.test']);
    }

    /** @param array<string, mixed> $overrides */
    private function setting(array $overrides = []): WhatsappSetting
    {
        return WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            ...$overrides,
        ]);
    }

    private const MESSAGE_EVENTS = [
        'message.received',
        'message.sent',
        'message.delivered',
        'message.read',
        'message.failed',
    ];

    public function test_webhook_is_created_after_api_key_with_message_events(): void
    {
        Http::fake([
            'gateway.test/api/v1/auth/register' => Http::response(['success' => true, 'data' => [
                'tenant' => ['id' => 't1', 'name' => 'Klinik', 'key' => 'tenant-uji'],
                'token' => ['accessToken' => 'access-uji', 'refreshToken' => 'refresh-uji'],
                'user' => [],
            ]], 201),
            'gateway.test/api/v1/api-keys' => Http::response(['success' => true, 'data' => ['id' => 'k1', 'key' => 'api-uji']], 201),
            'gateway.test/api/v1/webhooks' => Http::response(['success' => true, 'data' => ['id' => 'w1', 'secret' => 'secret-uji']], 201),
        ]);

        $setting = app(WhatsappGatewayProvisioningService::class)->ensure($this->setting());

        $this->assertSame('tenant-uji', $setting->gateway_tenant_key);
        $this->assertSame('api-uji', $setting->gateway_api_key);
        $this->assertSame('secret-uji', $setting->gateway_webhook_secret);
        $this->assertNull($setting->gateway_password);
        $this->assertTrue($setting->provisioned());
        $this->assertNotEmpty($setting->gateway_webhook_token);

        // Dibaca ulang dari basis data supaya penyimpanan yang gagal diam-diam
        // tidak lolos hanya karena atribut di memori sudah berubah.
        $fresh = $setting->fresh();
        $this->assertSame('tenant-uji', $fresh->gateway_tenant_key);
        $this->assertSame('api-uji', $fresh->gateway_api_key);
        $this->assertSame('secret-uji', $fresh->gateway_webhook_secret);
        $this->assertNull($fresh->gateway_password);

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/webhooks'
            && $request->hasHeader('x-tenant-key', 'tenant-uji')
            && $request->hasHeader('X-API-Key', 'api-uji')
            && str_ends_with((string) $request['url'], '/api/whatsapp/webhook/'.$fresh->gateway_webhook_token)
            && $request['events'] === self::MESSAGE_EVENTS);

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/auth/register'
            && $request['name'] === $this->tenant->name
            && $request['organizationName'] === $this->tenant->name
            && $request['email'] === 'tenant-'.$this->tenant->id.'@gateway.local'
            && mb_strlen((string) $request['password']) === 24);

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/api-keys'
            && $request->hasHeader('x-tenant-key', 'tenant-uji')
            && $request->hasHeader('Authorization', 'Bearer access-uji'));
    }

    public function test_retry_after_failed_api_key_step_logs_in_instead_of_registering_again(): void
    {
        Http::fake([
            'gateway.test/api/v1/auth/login' => Http::response(['success' => true, 'data' => [
                'tenantKey' => 'tenant-uji',
                'token' => ['accessToken' => 'access-login', 'refreshToken' => 'refresh-login'],
                'user' => [],
            ]], 200),
            'gateway.test/api/v1/api-keys' => Http::response(['success' => true, 'data' => ['id' => 'k2', 'key' => 'api-baru']], 201),
            'gateway.test/api/v1/webhooks' => Http::response(['success' => true, 'data' => ['id' => 'w2', 'secret' => 'secret-baru']], 201),
        ]);

        $setting = app(WhatsappGatewayProvisioningService::class)->ensure($this->setting([
            'gateway_tenant_key' => 'tenant-uji',
            'gateway_password' => 'rahasia-uji',
        ]));

        $this->assertSame('api-baru', $setting->gateway_api_key);
        $this->assertSame('secret-baru', $setting->gateway_webhook_secret);
        $this->assertNull($setting->gateway_password);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/auth/register'));
        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/auth/login'
            && $request['email'] === 'tenant-'.$this->tenant->id.'@gateway.local'
            && $request['password'] === 'rahasia-uji');
    }

    public function test_complete_credentials_do_not_call_the_gateway(): void
    {
        Http::fake();

        $setting = app(WhatsappGatewayProvisioningService::class)->ensure($this->setting([
            'gateway_tenant_key' => 'tenant-uji',
            'gateway_api_key' => 'api-uji',
            'gateway_webhook_secret' => 'secret-uji',
        ]));

        Http::assertNothingSent();
        $this->assertTrue($setting->provisioned());
    }

    /**
     * Kredensial akun lengkap tetapi webhook belum pernah dibuat: lanjutkan
     * dari langkah webhook saja, tanpa login atau mendaftar ulang.
     */
    public function test_missing_webhook_secret_resumes_without_registering_again(): void
    {
        Http::fake([
            'gateway.test/api/v1/webhooks' => Http::response(['success' => true, 'data' => ['id' => 'w1', 'secret' => 'secret-uji']], 201),
        ]);

        $setting = app(WhatsappGatewayProvisioningService::class)->ensure($this->setting([
            'gateway_tenant_key' => 'tenant-uji',
            'gateway_api_key' => 'api-uji',
        ]));

        $this->assertSame('secret-uji', $setting->gateway_webhook_secret);
        $this->assertTrue($setting->provisioned());

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/auth/'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api-keys'));
        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/webhooks'
            && str_ends_with((string) $request['url'], '/api/whatsapp/webhook/'.$setting->gateway_webhook_token)
            && $request['events'] === self::MESSAGE_EVENTS);
    }

    /**
     * Tanpa kata sandi tersimpan, akun yang sudah terdaftar tidak bisa
     * dimasuki lagi. Mendaftar ulang akan membuat tenant gateway ganda.
     */
    public function test_tenant_key_without_password_is_rejected_without_calling_the_gateway(): void
    {
        Http::fake();
        Log::spy();

        try {
            app(WhatsappGatewayProvisioningService::class)->ensure($this->setting([
                'gateway_tenant_key' => 'tenant-uji',
            ]));
            $this->fail('Seharusnya melempar.');
        } catch (WhatsappGatewayException $e) {
            $this->assertSame('GATEWAY_PASSWORD_MISSING', $e->code);
        }

        Http::assertNothingSent();
        Log::shouldHaveReceived('error')->once();
    }

    /**
     * API key gagal dibuat: kredensial pendaftaran harus tetap tersimpan agar
     * percobaan berikutnya cukup login, tidak mendaftar ulang.
     */
    public function test_failed_api_key_step_keeps_registration_password_for_retry(): void
    {
        Http::fake([
            'gateway.test/api/v1/auth/register' => Http::response(['success' => true, 'data' => [
                'tenant' => ['id' => 't1', 'name' => 'Klinik', 'key' => 'tenant-uji'],
                'token' => ['accessToken' => 'access-uji', 'refreshToken' => 'refresh-uji'],
                'user' => [],
            ]], 201),
            'gateway.test/api/v1/api-keys' => Http::response([
                'success' => false,
                'message' => 'Gateway sedang bermasalah.',
                'error' => ['code' => 'INTERNAL_ERROR'],
            ], 500),
        ]);

        $setting = $this->setting();

        try {
            app(WhatsappGatewayProvisioningService::class)->ensure($setting);
            $this->fail('Seharusnya melempar.');
        } catch (WhatsappGatewayException $e) {
            $this->assertSame(500, $e->status);
        }

        $fresh = $setting->fresh();
        $this->assertSame('tenant-uji', $fresh->gateway_tenant_key);
        $this->assertNull($fresh->gateway_api_key);
        $this->assertNotNull($fresh->gateway_password);
        $this->assertFalse($fresh->provisioned());
    }
}
