<?php

namespace Tests\Feature\Whatsapp;

use App\Actions\Whatsapp\RegisterGatewayWebhookAction;
use App\Models\WhatsappSetting;
use App\Services\WhatsappGatewayProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pendaftaran webhook mode manual: klinik hanya punya API key, tanpa tenant
 * key. Yang menentukan bukan kredensialnya melainkan APP_URL — gateway tidak
 * bisa memanggil alamat lokal, jadi memanggil gateway untuk alamat itu hanya
 * membuang waktu.
 */
class GatewayWebhookRegistrationTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const EVENTS = [
        'message.received',
        'message.sent',
        'message.delivered',
        'message.read',
        'message.failed',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        config([
            'whatsapp_gateway.base_url' => 'https://gateway.test',
            'app.url' => 'https://klinik.contoh.com',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function setting(array $overrides = []): WhatsappSetting
    {
        return WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            'gateway_api_key' => 'api-uji',
            ...$overrides,
        ]);
    }

    private function fakeGateway(): void
    {
        Http::preventStrayRequests();
        Http::fake(['gateway.test/api/v1/webhooks' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => ['id' => 'w1', 'secret' => 'secret-uji'],
        ], 201)]);
    }

    public function test_it_registers_with_api_key_only_and_stores_the_secret(): void
    {
        $this->fakeGateway();

        $setting = $this->setting();

        $this->assertTrue(app(RegisterGatewayWebhookAction::class)->handle($setting));

        $fresh = $setting->fresh();
        $this->assertSame('secret-uji', $fresh->gateway_webhook_secret);
        $this->assertNotEmpty($fresh->gateway_webhook_token);

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/webhooks'
            && $request->hasHeader('X-API-Key', 'api-uji')
            && ! $request->hasHeader('x-tenant-key')
            && $request['url'] === 'https://klinik.contoh.com/api/whatsapp/webhook/'.$fresh->gateway_webhook_token
            && $request['events'] === self::EVENTS);
    }

    /** Rahasia hanya dikembalikan sekali; pendaftaran ulang membuat webhook ganda. */
    public function test_a_registered_webhook_is_not_created_twice(): void
    {
        $this->fakeGateway();

        $setting = $this->setting();
        $action = app(RegisterGatewayWebhookAction::class);

        $this->assertTrue($action->handle($setting));
        $this->assertFalse($action->handle($setting->fresh()));

        Http::assertSentCount(1);
    }

    public function test_a_local_app_url_is_refused_without_calling_the_gateway(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        config(['app.url' => 'http://localhost:8123']);

        $this->assertFalse(app(RegisterGatewayWebhookAction::class)->handle($this->setting()));

        Http::assertNothingSent();
    }

    public function test_provisioning_in_manual_mode_registers_the_webhook(): void
    {
        $this->fakeGateway();

        $setting = app(WhatsappGatewayProvisioningService::class)->ensure($this->setting());

        $this->assertSame('secret-uji', $setting->fresh()->gateway_webhook_secret);

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/webhooks'
            && ! $request->hasHeader('x-tenant-key'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/auth/')
            || str_contains($request->url(), '/api-keys'));
    }

    /** APP_URL lokal tidak boleh membuat penyiapan sesi gagal. */
    public function test_provisioning_in_manual_mode_skips_a_local_app_url(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        config(['app.url' => 'http://localhost:8123']);

        $setting = $this->setting(['gateway_session_id' => 'sesi-uji']);

        $result = app(WhatsappGatewayProvisioningService::class)->ensure($setting);

        Http::assertNothingSent();
        $this->assertSame('api-uji', $result->fresh()->gateway_api_key);
        $this->assertSame('sesi-uji', $result->fresh()->gateway_session_id);
        $this->assertNull($result->fresh()->gateway_webhook_secret);
    }
}
