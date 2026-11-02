<?php

namespace Tests\Feature\Inbox;

use App\Enums\ConversationChannel;
use App\Enums\MessageDirection;
use App\Jobs\ProcessInboundMessageJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pesan masuk dari kedua pintu WhatsApp harus mendarat di kotak masuk —
 * termasuk saat gateway mengulang kiriman yang sama.
 */
class InboundMessageRecordingTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const WAHA_TOKEN = 'token-uji';

    private const APP_SECRET = 'rahasia-uji';

    private const PHONE_NUMBER_ID = '109876543210';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        config([
            'waha.webhook_token' => self::WAHA_TOKEN,
            'meta_whatsapp.verify_token' => 'token-meta',
            'meta_whatsapp.app_secret' => self::APP_SECRET,
        ]);

        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            'phone_number_id' => self::PHONE_NUMBER_ID,
        ]);

        // Job chatbot tidak ikut dijalankan: yang diuji di sini pencatatannya.
        Queue::fake();
    }

    /** @return array<string, mixed> */
    private function wahaPayload(array $overrides = []): array
    {
        return [
            'id' => 'waha-msg-1',
            'pushName' => 'Budi',
            'from' => '6281234567890@c.us',
            'to' => '628110000000@c.us',
            'body' => 'Halo, berapa harga facial?',
            'fromMe' => false,
            'hasMedia' => false,
            ...$overrides,
        ];
    }

    private function deliverWaha(array $payload): void
    {
        $this->postJson('/api/whatsapp/webhook/'.self::WAHA_TOKEN, [
            'session' => 'klinik-uji',
            'me' => ['id' => '628110000000@c.us'],
            'payload' => $payload,
        ])->assertOk();
    }

    /** @return array<string, mixed> */
    private function metaMessage(array $overrides = []): array
    {
        return [
            'from' => '6281234567890',
            'id' => 'wamid.1',
            'timestamp' => '1700000000',
            'type' => 'text',
            'text' => ['body' => 'Halo dari Meta'],
            'profile' => ['name' => 'Sari'],
            ...$overrides,
        ];
    }

    private function deliverMeta(array $message): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '102290129340398',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => self::PHONE_NUMBER_ID],
                        'messages' => [$message],
                    ],
                ]],
            ]],
        ];

        $raw = json_encode($payload);

        $this->call('POST', '/api/whatsapp/meta/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, self::APP_SECRET),
        ], $raw)->assertOk();
    }

    public function test_a_waha_message_is_recorded_and_raises_the_unread_count(): void
    {
        $this->deliverWaha($this->wahaPayload());

        $conversation = Conversation::query()->firstOrFail();

        $this->assertSame('6281234567890', $conversation->contact_phone);
        $this->assertSame('Budi', $conversation->contact_name);
        $this->assertSame(ConversationChannel::Waha, $conversation->channel);
        $this->assertSame(1, $conversation->unread_count);
        $this->assertNotNull($conversation->last_message_at);

        $message = Message::query()->firstOrFail();

        $this->assertSame(MessageDirection::In, $message->direction);
        $this->assertSame('waha-msg-1', $message->provider_message_id);
        $this->assertSame('Halo, berapa harga facial?', $message->body);

        Queue::assertPushed(ProcessInboundMessageJob::class);
    }

    public function test_a_meta_message_is_recorded_as_cloud_api_with_its_provider_id(): void
    {
        $this->deliverMeta($this->metaMessage());

        $conversation = Conversation::query()->firstOrFail();

        $this->assertSame(ConversationChannel::CloudApi, $conversation->channel);
        $this->assertSame('Sari', $conversation->contact_name);

        $message = Message::query()->firstOrFail();

        $this->assertSame('wamid.1', $message->provider_message_id);
        $this->assertSame(MessageDirection::In, $message->direction);
    }

    /**
     * Gateway mengirim ulang webhook yang lambat dibalas. Pesan yang sama tidak
     * boleh tercatat dua kali dan tidak boleh menaikkan penghitung dua kali.
     */
    public function test_a_repeated_provider_message_id_is_not_recorded_twice(): void
    {
        $this->deliverWaha($this->wahaPayload());
        $this->deliverWaha($this->wahaPayload());

        $this->assertSame(1, Message::query()->count());
        $this->assertSame(1, Conversation::query()->firstOrFail()->unread_count);

        $this->deliverMeta($this->metaMessage());
        $this->deliverMeta($this->metaMessage());

        $this->assertSame(1, Message::query()->where('provider_message_id', 'wamid.1')->count());
    }
}
