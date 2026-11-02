<?php

namespace Tests\Feature\Inbox;

use App\Enums\ConversationChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Events\InboxMessageStatusUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Status kirim dari webhook Meta: centang hanya boleh maju, dan hanya pesan
 * milik nomor klinik yang dikenal yang boleh berubah.
 */
class MessageStatusUpdateTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const APP_SECRET = 'rahasia-uji';

    private const PHONE_NUMBER_ID = '109876543210';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        config([
            'meta_whatsapp.verify_token' => 'token-meta',
            'meta_whatsapp.app_secret' => self::APP_SECRET,
        ]);

        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            'phone_number_id' => self::PHONE_NUMBER_ID,
        ]);

        Event::fake([InboxMessageStatusUpdated::class]);
    }

    private function outboundMessage(string $providerMessageId, MessageStatus $status = MessageStatus::Sent): Message
    {
        $conversation = Conversation::query()->firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'contact_phone' => '6281234567890'],
            ['channel' => ConversationChannel::CloudApi],
        );

        return $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'body' => 'Halo dari klinik',
            'provider_message_id' => $providerMessageId,
            'status' => $status,
            'sent_at' => now(),
        ]);
    }

    /** @param  list<array<string, mixed>>  $statuses */
    private function deliverStatuses(array $statuses, string $phoneNumberId = self::PHONE_NUMBER_ID): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '102290129340398',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => $phoneNumberId],
                        'statuses' => $statuses,
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

    public function test_delivered_and_read_statuses_advance_the_message_and_broadcast(): void
    {
        $message = $this->outboundMessage('wamid.out-1');

        $this->deliverStatuses([
            ['id' => 'wamid.out-1', 'status' => 'delivered', 'timestamp' => '1700000001'],
        ]);

        $this->assertSame(MessageStatus::Delivered, $message->refresh()->status);

        Event::assertDispatched(InboxMessageStatusUpdated::class, fn (InboxMessageStatusUpdated $event): bool => $event->tenantId === $this->tenant->id
            && $event->conversationId === (int) $message->conversation_id
            && $event->messageId === (int) $message->id
            && $event->direction === MessageDirection::Out
            && $event->status === MessageStatus::Delivered);

        $this->deliverStatuses([
            ['id' => 'wamid.out-1', 'status' => 'read', 'timestamp' => '1700000002'],
        ]);

        $this->assertSame(MessageStatus::Read, $message->refresh()->status);

        Event::assertDispatched(InboxMessageStatusUpdated::class, fn (InboxMessageStatusUpdated $event): bool => $event->status === MessageStatus::Read);
    }

    public function test_an_unknown_status_is_ignored(): void
    {
        $message = $this->outboundMessage('wamid.out-2');

        $this->deliverStatuses([
            ['id' => 'wamid.out-2', 'status' => 'deleted', 'timestamp' => '1700000001'],
        ]);

        $this->assertSame(MessageStatus::Sent, $message->refresh()->status);
        Event::assertNotDispatched(InboxMessageStatusUpdated::class);
    }

    public function test_an_unknown_phone_number_id_changes_nothing(): void
    {
        $message = $this->outboundMessage('wamid.out-3');

        $this->deliverStatuses([
            ['id' => 'wamid.out-3', 'status' => 'read', 'timestamp' => '1700000001'],
        ], phoneNumberId: '999999999999');

        $this->assertSame(MessageStatus::Sent, $message->refresh()->status);
        Event::assertNotDispatched(InboxMessageStatusUpdated::class);
    }

    public function test_a_read_message_is_not_downgraded_to_delivered(): void
    {
        $message = $this->outboundMessage('wamid.out-4', MessageStatus::Read);

        $this->deliverStatuses([
            ['id' => 'wamid.out-4', 'status' => 'delivered', 'timestamp' => '1700000001'],
        ]);

        $this->assertSame(MessageStatus::Read, $message->refresh()->status);
        Event::assertNotDispatched(InboxMessageStatusUpdated::class);
    }

    public function test_failed_does_not_overwrite_a_read_message(): void
    {
        $message = $this->outboundMessage('wamid.out-5', MessageStatus::Read);

        $this->deliverStatuses([
            ['id' => 'wamid.out-5', 'status' => 'failed', 'timestamp' => '1700000001'],
        ]);

        $this->assertSame(MessageStatus::Read, $message->refresh()->status);
        Event::assertNotDispatched(InboxMessageStatusUpdated::class);
    }
}
