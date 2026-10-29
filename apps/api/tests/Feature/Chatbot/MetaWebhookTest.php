<?php

namespace Tests\Feature\Chatbot;

use App\Jobs\ProcessInboundMessageJob;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pintu masuk pesan WhatsApp Cloud API (Meta). Route-nya publik dan tanpa
 * token di URL, jadi yang diuji di sini adalah satu-satunya penjaganya —
 * tanda tangan HMAC — plus pesan mana yang memang tidak perlu diproses.
 */
class MetaWebhookTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const VERIFY_TOKEN = 'token-uji-meta';

    private const APP_SECRET = 'rahasia-uji';

    private const PHONE_NUMBER_ID = '109876543210';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        config([
            'meta_whatsapp.verify_token' => self::VERIFY_TOKEN,
            'meta_whatsapp.app_secret' => self::APP_SECRET,
        ]);

        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            'phone_number_id' => self::PHONE_NUMBER_ID,
        ]);

        Queue::fake();
    }

    private function handshake(array $query): TestResponse
    {
        return $this->get('/api/whatsapp/meta/webhook?'.http_build_query($query));
    }

    /**
     * Kirim payload apa adanya, dengan tanda tangan dihitung dari isi mentah
     * yang benar-benar dikirim — persis seperti Meta melakukannya.
     */
    private function deliver(array $payload, string $secret = self::APP_SECRET): TestResponse
    {
        $raw = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $raw, $secret);

        return $this->call('POST', '/api/whatsapp/meta/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $raw);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function event(array $message, string $phoneNumberId = self::PHONE_NUMBER_ID): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '102290129340398',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '628110000000',
                            'phone_number_id' => $phoneNumberId,
                        ],
                        'messages' => [$message],
                    ],
                ]],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function textMessage(string $body = 'Halo, berapa harga facial?'): array
    {
        return [
            'from' => '6281234567890',
            'id' => 'wamid.1',
            'timestamp' => '1700000000',
            'type' => 'text',
            'text' => ['body' => $body],
        ];
    }

    public function test_the_handshake_echoes_the_challenge(): void
    {
        $this->handshake([
            'hub.mode' => 'subscribe',
            'hub.verify_token' => self::VERIFY_TOKEN,
            'hub.challenge' => '1158201444',
        ])->assertOk()->assertContent('1158201444');
    }

    public function test_a_wrong_verify_token_is_forbidden(): void
    {
        $this->handshake([
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'token-palsu',
            'hub.challenge' => '1158201444',
        ])->assertForbidden();
    }

    /**
     * Tanpa secret yang benar, siapa pun bisa memalsukan pesan masuk dan
     * memicu balasan atas nama klinik.
     */
    public function test_a_wrong_signature_is_forbidden(): void
    {
        $this->deliver($this->event($this->textMessage()), secret: 'rahasia-palsu')
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_a_valid_text_message_is_queued(): void
    {
        $this->deliver($this->event($this->textMessage()))->assertOk();

        Queue::assertPushed(
            ProcessInboundMessageJob::class,
            fn (ProcessInboundMessageJob $job) => $job->tenantId === $this->tenant->id
                && $job->senderPhone === '6281234567890'
                && $job->body === 'Halo, berapa harga facial?',
        );
    }

    /** Status centang biru tiba lewat webhook yang sama; tidak ada yang perlu dijawab. */
    public function test_delivery_statuses_are_ignored(): void
    {
        $this->deliver([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '102290129340398',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => self::PHONE_NUMBER_ID],
                        'statuses' => [[
                            'id' => 'wamid.1',
                            'status' => 'delivered',
                            'timestamp' => '1700000001',
                            'recipient_id' => '6281234567890',
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        Queue::assertNothingPushed();
    }

    /** Nomor Meta yang belum didaftarkan klinik mana pun tidak boleh menebak pemiliknya. */
    public function test_an_unknown_phone_number_id_is_ignored(): void
    {
        $this->deliver($this->event($this->textMessage(), phoneNumberId: '999999999'))
            ->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_media_messages_are_ignored(): void
    {
        $this->deliver($this->event([
            'from' => '6281234567890',
            'id' => 'wamid.2',
            'timestamp' => '1700000000',
            'type' => 'image',
            'image' => ['id' => 'media-1', 'mime_type' => 'image/jpeg'],
        ]))->assertOk();

        Queue::assertNothingPushed();
    }
}
