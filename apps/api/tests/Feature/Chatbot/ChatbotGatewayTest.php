<?php

namespace Tests\Feature\Chatbot;

use App\Enums\ConversationChannel;
use App\Jobs\ProcessInboundMessageJob;
use App\Models\ChatbotSetting;
use App\Models\ChatMessage;
use App\Models\WhatsappSetting;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Balasan chatbot lewat gateway WhatsApp internal.
 *
 * Yang diuji adalah sambungan yang paling mudah putus tanpa terlihat: pesan
 * benar-benar keluar lewat gateway, id pesannya tersimpan untuk pencocokan
 * status, indikator mengetik menyala dan padam, dan klinik yang gatewaynya
 * belum siap tidak membayar panggilan AI untuk balasan yang langsung dibuang.
 */
class ChatbotGatewayTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const SESSION_ID = '11111111-1111-1111-1111-111111111111';

    private const SENDER = '6281234567890';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        config([
            'whatsapp_gateway.base_url' => 'https://gateway.test',
            'services.deepseek.api_key' => 'kunci-uji',
            'services.deepseek.base_url' => 'https://ai.uji',
            'services.deepseek.model' => 'model-uji',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function gatewaySetting(array $overrides = []): WhatsappSetting
    {
        return WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            'gateway_tenant_key' => 'tenant-uji',
            'gateway_api_key' => 'api-uji',
            'gateway_webhook_token' => 'token-uji',
            'gateway_webhook_secret' => 'secret-uji',
            'gateway_session_id' => self::SESSION_ID,
            ...$overrides,
        ]);
    }

    /** @return array<string, mixed> */
    private static function answer(string $content): array
    {
        return ['choices' => [[
            'message' => ['role' => 'assistant', 'content' => $content],
            'finish_reason' => 'stop',
        ]]];
    }

    private function runJob(): void
    {
        (new ProcessInboundMessageJob(
            $this->tenant->id,
            self::SENDER,
            'Halo',
            ConversationChannel::Gateway,
        ))->handle();
    }

    public function test_chatbot_shows_typing_and_sends_reply_via_gateway(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->gatewaySetting();

        Http::fake([
            'ai.uji/*' => Http::response(self::answer('Halo, ada yang bisa dibantu?')),
            'gateway.test/api/v1/sessions/*/presence' => Http::response(['success' => true, 'data' => []], 202),
            'gateway.test/api/v1/messages/send' => Http::response(['success' => true, 'data' => [
                'messageId' => 'uuid-balasan', 'status' => 'QUEUED',
            ]], 202),
        ]);

        $this->runJob();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/presence')
            && $request['presence'] === 'composing');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/presence')
            && $request['presence'] === 'paused');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/messages/send')
            && $request['text'] === 'Halo, ada yang bisa dibantu?');

        $this->assertDatabaseHas('messages', ['provider_message_id' => 'uuid-balasan']);
    }

    /**
     * Gateway yang belum siap tidak boleh tetap memanggil penyedia AI:
     * balasannya langsung dibuang, dan itu terjadi pada setiap pesan masuk
     * selama gatewaynya putus.
     */
    public function test_no_ai_call_is_made_when_the_gateway_is_not_provisioned(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        // Tanpa rahasia webhook, klinik belum dianggap terhubung ke gateway.
        $this->gatewaySetting(['gateway_webhook_secret' => null]);

        Http::fake();

        $this->runJob();

        Http::assertNothingSent();
        $this->assertSame(0, ChatMessage::query()->count());
    }

    /**
     * Mode manual: API key, sesi, dan rahasia webhook cukup. Tenant key hanya
     * pelengkap alur register yang tidak dijalani klinik ini.
     */
    public function test_manual_mode_without_tenant_key_still_replies(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->gatewaySetting(['gateway_tenant_key' => null]);

        Http::fake([
            'ai.uji/*' => Http::response(self::answer('Halo, ada yang bisa dibantu?')),
            'gateway.test/api/v1/sessions/*/presence' => Http::response(['success' => true, 'data' => []], 202),
            'gateway.test/api/v1/messages/send' => Http::response(['success' => true, 'data' => [
                'messageId' => 'uuid-manual', 'status' => 'QUEUED',
            ]], 202),
        ]);

        $this->runJob();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/messages/send')
            && $request['text'] === 'Halo, ada yang bisa dibantu?');
        $this->assertDatabaseHas('messages', ['provider_message_id' => 'uuid-manual']);
    }

    /** Tanpa baris pengaturan sama sekali pun gateway dianggap belum siap. */
    public function test_no_ai_call_is_made_without_any_gateway_setting(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);

        Http::fake();

        $this->runJob();

        Http::assertNothingSent();
        $this->assertSame(0, ChatMessage::query()->count());
    }

    /**
     * Balasan kosong tetap harus memadamkan indikator: pasien yang melihat
     * "sedang mengetik" tanpa balasan menunggu sesuatu yang tidak akan datang.
     */
    public function test_paused_is_sent_when_the_answer_is_null(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->gatewaySetting();

        Http::fake(['gateway.test/*' => Http::response(['success' => true, 'data' => []], 202)]);

        $this->mock(ChatbotService::class, function ($mock): void {
            $mock->shouldReceive('isActive')->once()->andReturn(true);
            $mock->shouldReceive('reply')->once()->andReturnNull();
        });

        $this->runJob();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/presence')
            && $request['presence'] === 'paused');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/messages/send'));
    }

    /** Jalur fallback saat AI melempar pun harus menutup indikator mengetik. */
    public function test_paused_is_sent_when_the_chatbot_throws(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->gatewaySetting();

        Http::fake([
            'gateway.test/api/v1/sessions/*/presence' => Http::response(['success' => true, 'data' => []], 202),
            'gateway.test/api/v1/messages/send' => Http::response(['success' => true, 'data' => [
                'messageId' => 'fallback-uji', 'status' => 'QUEUED',
            ]], 202),
        ]);

        $this->mock(ChatbotService::class, function ($mock): void {
            $mock->shouldReceive('isActive')->once()->andReturn(true);
            $mock->shouldReceive('reply')->once()->andThrow(new RuntimeException('AI mati'));
        });

        $this->runJob();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/presence')
            && $request['presence'] === 'paused');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/messages/send')
            && $request['text'] === __('chatbot.fallback'));
        $this->assertDatabaseHas('messages', ['provider_message_id' => 'fallback-uji']);
    }
}
