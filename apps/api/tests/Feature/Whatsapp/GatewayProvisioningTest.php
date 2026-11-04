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

    public function test_first_provisioning_registers_tenant_and_api_key(): void
    {
        Http::fake([
            'gateway.test/api/v1/auth/register' => Http::response(['success' => true, 'data' => [
                'tenant' => ['id' => 't1', 'name' => 'Klinik', 'key' => 'tenant-uji'],
                'token' => ['accessToken' => 'access-uji', 'refreshToken' => 'refresh-uji'],
                'user' => [],
            ]], 201),
            'gateway.test/api/v1/api-keys' => Http::response(['success' => true, 'data' => ['id' => 'k1', 'key' => 'api-uji']], 201),
        ]);

        $setting = app(WhatsappGatewayProvisioningService::class)->ensure($this->setting());

        $this->assertSame('tenant-uji', $setting->gateway_tenant_key);
        $this->assertSame('api-uji', $setting->gateway_api_key);
        $this->assertNull($setting->gateway_password);
        $this->assertTrue($setting->provisioned());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/webhooks'));

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
        ]);

        $setting = app(WhatsappGatewayProvisioningService::class)->ensure($this->setting([
            'gateway_tenant_key' => 'tenant-uji',
            'gateway_password' => 'rahasia-uji',
        ]));

        $this->assertSame('api-baru', $setting->gateway_api_key);
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
        ]));

        Http::assertNothingSent();
        $this->assertTrue($setting->provisioned());
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
}
