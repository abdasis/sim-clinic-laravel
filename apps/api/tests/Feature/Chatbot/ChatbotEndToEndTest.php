<?php

namespace Tests\Feature\Chatbot;

use App\Models\ChatbotSetting;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        config([
            'waha.webhook_token' => 'tok',
            // Antrian dijalankan langsung supaya rantainya utuh dalam satu
            // permintaan; di server yang bekerja adalah proses queue:work.
            'queue.default' => 'sync',
            'services.deepseek.api_key' => 'kunci',
            'services.deepseek.base_url' => 'https://ai.uji',
            'services.deepseek.model' => 'model',
        ]);

        WahaSetting::create(['base_url' => 'https://waha.uji', 'api_key' => 'waha-key']);
        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);
    }

    private function incoming(): void
    {
        $this->postJson('/api/whatsapp/webhook/tok', [
            'event' => 'message',
            'session' => 'klinik-uji',
            'payload' => [
                'from' => '6281234567890@c.us',
                'body' => 'halo, berapa harga facial?',
                'fromMe' => false,
                'hasMedia' => false,
            ],
        ])->assertOk();
    }

    public function test_a_patient_message_ends_up_as_a_sent_reply(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);

        Http::fake([
            'ai.uji/*' => Http::response(['choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'Halo, ada yang bisa dibantu?'],
                'finish_reason' => 'stop',
            ]]]),
            'waha.uji/*' => Http::response(['ok' => true]),
        ]);

        $this->incoming();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'ai.uji/chat/completions'));
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'waha.uji/api/sendText')
                && $request->data()['text'] === 'Halo, ada yang bisa dibantu?'
                && $request->data()['chatId'] === '6281234567890@c.us';
        });

        $this->assertDatabaseHas('chat_messages', ['direction' => 'out', 'content' => 'Halo, ada yang bisa dibantu?']);
    }

    /** Chatbot mati berarti AI tidak dipanggil sama sekali, bukan sekadar tidak dibalas. */
    public function test_nothing_is_called_when_the_chatbot_is_off(): void
    {
        ChatbotSetting::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);
        Http::fake();

        $this->incoming();

        Http::assertNothingSent();
    }

    /**
     * chatbot:doctor harus benar-benar membedakan siap dari tidak siap; kalau
     * tidak, perintahnya justru menyesatkan orang yang sedang mencari masalah.
     */
    public function test_the_doctor_command_tells_ready_from_not_ready(): void
    {
        $setting = ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->artisan('chatbot:doctor')->assertSuccessful();

        $setting->update(['is_active' => false]);
        $this->artisan('chatbot:doctor')->assertFailed();

        $setting->update(['is_active' => true]);
        config(['services.deepseek.api_key' => null]);
        $this->artisan('chatbot:doctor')->assertFailed();
    }
}
