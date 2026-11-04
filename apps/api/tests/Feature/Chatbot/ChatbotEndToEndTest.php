<?php

namespace Tests\Feature\Chatbot;

use App\Models\ChatbotSetting;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Seluruh rantai tanpa satu pun yang dipalsukan selain HTTP keluar:
 * webhook -> antrian -> ChatbotService -> gateway WhatsApp.
 *
 * Tes lain memeriksa tiap bagian sendiri-sendiri dan tetap hijau walau
 * sambungan antarbagiannya putus. Yang ini memastikan pesan pasien benar-benar
 * berakhir sebagai pesan terkirim — gejala yang dikeluhkan di lapangan selalu
 * "tidak ada balasan", bukan "bagian X salah".
 */
class ChatbotEndToEndTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const TOKEN = 'token-uji';

    private const SECRET = 'rahasia-uji';

    private const SESSION_ID = '11111111-1111-1111-1111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        config([
            'whatsapp_gateway.base_url' => 'https://gateway.test',
            // Antrian dijalankan langsung supaya rantainya utuh dalam satu
            // permintaan; di server yang bekerja adalah proses queue:work.
            'queue.default' => 'sync',
            'services.deepseek.api_key' => 'kunci',
            'services.deepseek.base_url' => 'https://ai.uji',
            'services.deepseek.model' => 'model',
        ]);

        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            'gateway_tenant_key' => 'tenant-uji',
            'gateway_api_key' => 'api-uji',
            'gateway_webhook_token' => self::TOKEN,
            'gateway_webhook_secret' => self::SECRET,
            'gateway_session_id' => self::SESSION_ID,
        ]);
    }

    /** Kirim pesan masuk dengan header HMAC yang sah, persis seperti gateway. */
    private function incoming(): TestResponse
    {
        $body = json_encode([
            'messageId' => '0f8fad5b-d9cb-469f-a165-70867728950e',
            'sessionId' => 'klinik-uji',
            'remoteJid' => '6281234567890@s.whatsapp.net',
            'remoteJidAlt' => null,
            'type' => 'TEXT',
            'content' => 'halo, berapa harga facial?',
        ]);
        $timestamp = (string) time();
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);

        $headers = $this->transformHeadersToServerVars([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
            'X-Webhook-ID' => 'evt-uji',
            'X-Webhook-Event' => 'message.received',
            'X-Webhook-Timestamp' => $timestamp,
            'X-Webhook-Signature' => $signature,
        ]);

        return $this->call('POST', '/api/whatsapp/webhook/'.self::TOKEN, [], [], [], $headers, $body);
    }

    /** @return array<string, mixed> */
    private static function answer(string $content): array
    {
        return ['choices' => [[
            'message' => ['role' => 'assistant', 'content' => $content],
            'finish_reason' => 'stop',
        ]]];
    }

    private function fakeGateway(string $reply): void
    {
        Http::fake([
            'ai.uji/*' => Http::response(self::answer($reply)),
            'gateway.test/api/v1/sessions/*/presence' => Http::response(['success' => true, 'data' => []], 202),
            'gateway.test/api/v1/messages/send' => Http::response(['success' => true, 'data' => [
                'messageId' => 'balasan-uji', 'status' => 'QUEUED',
            ]], 202),
        ]);
    }

    public function test_a_patient_message_ends_up_as_a_sent_reply(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->fakeGateway('Halo, ada yang bisa dibantu?');

        $this->incoming()->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'ai.uji/chat/completions'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'gateway.test/api/v1/messages/send')
            && $request['text'] === 'Halo, ada yang bisa dibantu?'
            && $request['to'] === '6281234567890');

        $this->assertDatabaseHas('chat_messages', ['direction' => 'out', 'content' => 'Halo, ada yang bisa dibantu?']);
    }

    /**
     * Indikator mengetik menemani jeda penyusunan balasan, jadi urutannya
     * yang penting: menyala sebelum AI dipanggil, padam setelah pesan pergi.
     */
    public function test_the_typing_indicator_wraps_the_whole_reply(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->fakeGateway('Halo, ada yang bisa dibantu?');

        $this->incoming()->assertOk();

        $recorded = Http::recorded();

        $at = fn (callable $match) => $recorded->search(fn (array $pair) => $match($pair[0]));

        $composingAt = $at(fn ($request) => str_contains($request->url(), '/presence')
            && $request['presence'] === 'composing');
        $pausedAt = $at(fn ($request) => str_contains($request->url(), '/presence')
            && $request['presence'] === 'paused');
        $sendAt = $at(fn ($request) => str_contains($request->url(), '/messages/send'));

        $this->assertNotFalse($composingAt, 'Indikator mengetik harus menyala.');
        $this->assertNotFalse($pausedAt, 'Indikator mengetik harus padam.');
        $this->assertLessThan($sendAt, $composingAt, 'Indikator harus menyala sebelum balasan dikirim.');
        $this->assertGreaterThan($sendAt, $pausedAt, 'Indikator baru padam setelah balasan dikirim.');
    }

    /** Chatbot mati berarti AI tidak dipanggil sama sekali, bukan sekadar tidak dibalas. */
    public function test_nothing_is_called_when_the_chatbot_is_off(): void
    {
        ChatbotSetting::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);
        Http::fake();

        $this->incoming()->assertOk();

        Http::assertNothingSent();
    }

    /**
     * chatbot:doctor harus benar-benar membedakan siap dari tidak siap; kalau
     * tidak, perintahnya justru menyesatkan orang yang sedang mencari masalah.
     */
    public function test_the_doctor_command_tells_ready_from_not_ready(): void
    {
        $setting = ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);

        Http::fake(['gateway.test/api/v1/sessions/*/status' => Http::response([
            'success' => true,
            'data' => ['sessionId' => 'klinik-uji', 'status' => 'CONNECTED'],
        ])]);

        $this->artisan('chatbot:doctor')->assertSuccessful();

        $setting->update(['is_active' => false]);
        $this->artisan('chatbot:doctor')->assertFailed();

        $setting->update(['is_active' => true]);
        config(['services.deepseek.api_key' => null]);
        $this->artisan('chatbot:doctor')->assertFailed();
    }
}
