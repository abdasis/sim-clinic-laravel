<?php

namespace Tests\Feature\Whatsapp;

use App\Enums\ConversationChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Jobs\ProcessInboundMessageJob;
use App\Models\Conversation;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pintu masuk webhook gateway WhatsApp.
 *
 * Route-nya publik dan memakai token per klinik di URL, lalu tanda tangan HMAC
 * atas isi mentah permintaan dengan rahasia klinik itu. Yang diuji di sini
 * adalah penjaganya: siapa yang boleh masuk dan pesan mana yang memang tidak
 * perlu diproses.
 */
class GatewayWebhookTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const TOKEN = 'token-gateway-uji';

    private const SECRET = 'rahasia-gateway-uji';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        config(['app.url' => 'http://localhost']);

        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            'gateway_webhook_token' => self::TOKEN,
            'gateway_webhook_secret' => self::SECRET,
        ]);

        Queue::fake();
    }

    /**
     * Kirim payload dengan header tanda tangan HMAC yang sah, persis seperti
     * gateway. Isi mentah ditandatangani apa adanya, bukan hasil parse ulang.
     *
     * @param  array<string, mixed>  $payload
     */
    private function sendWebhook(
        array $payload,
        string $event = 'message.received',
        ?int $timestamp = null,
        ?string $signature = null,
        string $token = self::TOKEN,
    ): TestResponse {
        $body = json_encode($payload);
        $timestamp ??= time();
        $signature ??= 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);

        $headers = $this->transformHeadersToServerVars([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
            'X-Webhook-ID' => 'evt-uji',
            'X-Webhook-Event' => $event,
            'X-Webhook-Timestamp' => (string) $timestamp,
            'X-Webhook-Signature' => $signature,
        ]);

        return $this->call('POST', '/api/whatsapp/webhook/'.$token, [], [], [], $headers, $body);
    }

    /** @return array<string, mixed> */
    private function receivedPayload(string $remoteJid = '6281234567890@s.whatsapp.net', ?string $remoteJidAlt = null): array
    {
        return [
            'messageId' => '0f8fad5b-d9cb-469f-a165-70867728950e',
            'sessionId' => 'klinik-uji',
            'remoteJid' => $remoteJid,
            'remoteJidAlt' => $remoteJidAlt,
            'type' => 'TEXT',
            'content' => 'Halo dari pelanggan',
            'at' => '2026-11-04T10:00:00.000Z',
        ];
    }

    public function test_valid_signature_records_inbound_and_dispatches_job(): void
    {
        $this->sendWebhook($this->receivedPayload())
            ->assertOk()
            ->assertJsonPath('data.status', 'diterima')
            ->assertJsonStructure(['data' => ['status'], 'meta']);

        $this->assertDatabaseHas('messages', [
            'provider_message_id' => '0f8fad5b-d9cb-469f-a165-70867728950e',
            'body' => 'Halo dari pelanggan',
            'direction' => MessageDirection::In->value,
        ]);

        $this->assertDatabaseHas('conversations', [
            'tenant_id' => $this->tenant->id,
            'contact_phone' => '6281234567890',
            'channel' => ConversationChannel::Gateway->value,
            'unread_count' => 1,
        ]);

        Queue::assertPushed(
            ProcessInboundMessageJob::class,
            fn (ProcessInboundMessageJob $job) => $job->tenantId === $this->tenant->id
                && $job->senderPhone === '6281234567890'
                && $job->body === 'Halo dari pelanggan'
                && $job->channel === ConversationChannel::Gateway,
        );
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->sendWebhook($this->receivedPayload(), signature: 'sha256='.str_repeat('0', 64))
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $this->sendWebhook($this->receivedPayload(), timestamp: time() - 600)
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('conversations', 0);
        Queue::assertNothingPushed();
    }

    public function test_non_numeric_timestamp_is_rejected(): void
    {
        $body = json_encode($this->receivedPayload());
        $signature = 'sha256='.hash_hmac('sha256', 'besok.'.$body, self::SECRET);

        $headers = $this->transformHeadersToServerVars([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
            'X-Webhook-Event' => 'message.received',
            'X-Webhook-Timestamp' => 'besok',
            'X-Webhook-Signature' => $signature,
        ]);

        $this->call('POST', '/api/whatsapp/webhook/'.self::TOKEN, [], [], [], $headers, $body)
            ->assertForbidden();
    }

    public function test_lid_sender_uses_remote_jid_alt(): void
    {
        $this->sendWebhook($this->receivedPayload('2399@lid', '6281234567890@s.whatsapp.net'))
            ->assertOk();

        $this->assertDatabaseHas('conversations', [
            'tenant_id' => $this->tenant->id,
            'contact_phone' => '6281234567890',
        ]);
        Queue::assertPushed(ProcessInboundMessageJob::class);
    }

    public function test_lid_without_alt_is_dropped_without_conversation(): void
    {
        Log::spy();
        Log::shouldReceive('channel')->with('chatbot')->andReturnSelf();

        $this->sendWebhook($this->receivedPayload('2399@lid'))->assertOk();

        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
        Queue::assertNothingPushed();
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_group_message_is_ignored(): void
    {
        $this->sendWebhook($this->receivedPayload('1203630@g.us'))->assertOk();

        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_non_text_message_is_ignored(): void
    {
        $this->sendWebhook([...$this->receivedPayload(), 'type' => 'IMAGE'])->assertOk();

        $this->assertDatabaseCount('conversations', 0);
        Queue::assertNothingPushed();
    }

    public function test_status_event_advances_message(): void
    {
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'contact_phone' => '6281234567890',
            'channel' => ConversationChannel::Gateway,
        ]);

        $message = $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'body' => 'Balasan klinik',
            'provider_message_id' => 'wamid-keluar-1',
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
        ]);

        $this->sendWebhook([
            'messageId' => 'wamid-keluar-1',
            'sessionId' => 'klinik-uji',
            'status' => 'read',
            'at' => '2026-11-04T10:01:00.000Z',
        ], 'message.read')
            ->assertOk()
            ->assertJsonPath('data.status', 'status diperbarui')
            ->assertJsonStructure(['data' => ['status'], 'meta']);

        $this->assertSame(MessageStatus::Read, $message->fresh()->status);
    }

    public function test_failed_status_marks_message_failed(): void
    {
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'contact_phone' => '6281234567890',
            'channel' => ConversationChannel::Gateway,
        ]);

        $message = $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'body' => 'Balasan klinik',
            'provider_message_id' => 'wamid-keluar-2',
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
        ]);

        $this->sendWebhook([
            'messageId' => 'wamid-keluar-2',
            'sessionId' => 'klinik-uji',
            'status' => 'failed',
            'at' => '2026-11-04T10:01:00.000Z',
        ], 'message.failed')->assertOk();

        $this->assertSame(MessageStatus::Failed, $message->fresh()->status);
    }

    public function test_status_for_unknown_message_is_acknowledged_with_accurate_reason(): void
    {
        $this->sendWebhook([
            'messageId' => 'wamid-tidak-ada',
            'sessionId' => 'klinik-uji',
            'status' => 'read',
            'at' => '2026-11-04T10:01:00.000Z',
        ], 'message.read')
            ->assertOk()
            ->assertJsonPath('data.status', 'pesan tidak ditemukan');
    }

    public function test_replayed_received_event_does_not_duplicate_message(): void
    {
        $payload = $this->receivedPayload();

        $this->sendWebhook($payload)->assertOk();
        $this->sendWebhook($payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'sudah diterima');

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseHas('conversations', [
            'contact_phone' => '6281234567890',
            'unread_count' => 1,
        ]);

        // Kiriman ulang tidak boleh memicu balasan chatbot kedua kali.
        Queue::assertPushed(ProcessInboundMessageJob::class, 1);
    }

    public function test_unknown_event_is_acknowledged_without_processing(): void
    {
        $this->sendWebhook(['foo' => 'bar'], 'session.status_changed')->assertOk();

        $this->assertDatabaseCount('conversations', 0);
        Queue::assertNothingPushed();
    }

    /**
     * Token asing diakui dengan 200, bukan 404.
     *
     * Gateway mengirim ulang apa pun yang tidak dibalas 200; membalas 404
     * hanya membuatnya mencoba lagi tanpa henti. Payload-nya tidak diproses,
     * dan tokennya tidak dicatat utuh di log.
     */
    public function test_unknown_token_is_acknowledged_without_processing(): void
    {
        Log::spy();
        Log::shouldReceive('channel')->with('chatbot')->andReturnSelf();

        $this->sendWebhook($this->receivedPayload(), token: 'tidak-dikenal')
            ->assertOk()
            ->assertJsonPath('data.status', 'token tidak dikenal');

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('conversations', 0);
        Queue::assertNothingPushed();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return ! array_key_exists('token', $context)
                    && isset($context['token_prefix'], $context['token_hash']);
            });
    }
}
