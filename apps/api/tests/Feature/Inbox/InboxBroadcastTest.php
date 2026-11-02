<?php

namespace Tests\Feature\Inbox;

use App\Actions\Inbox\RecordInboundMessageAction;
use App\Actions\Inbox\RecordOutboundMessageAction;
use App\Enums\ConversationChannel;
use App\Enums\MessageDirection;
use App\Events\InboxMessageCreated;
use App\Models\Tenant;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Siaran realtime kotak masuk: kontrak event, otorisasi kanal, dan pemicunya
 * dari kedua Action pencatat pesan.
 */
class InboxBroadcastTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Driver sungguhan dipakai supaya callback kanal benar-benar dijalankan;
        // driver log/null menerima semua langganan tanpa memeriksa apa pun.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => 'kunci-uji',
                'secret' => 'rahasia-uji',
                'app_id' => 'app-uji',
                'options' => [
                    'host' => '127.0.0.1',
                    'port' => 8080,
                    'scheme' => 'http',
                    'useTLS' => false,
                ],
            ],
        ]);

        // Kanal didaftarkan saat aplikasi boot memakai driver dari phpunit.xml
        // (null), yang menerima semua langganan tanpa memeriksa callback.
        // Daftarkan ulang ke driver reverb di atas agar yang diuji jalur
        // otorisasi yang sesungguhnya.
        require base_path('routes/channels.php');
    }

    public function test_event_contract_is_flat_and_scoped_to_the_tenant_channel(): void
    {
        $event = new InboxMessageCreated(7, 11, 22, MessageDirection::Out);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertSame('inbox.message', $event->broadcastAs());

        $channel = $event->broadcastOn();
        $this->assertInstanceOf(PrivateChannel::class, $channel);
        $this->assertSame('private-tenant.7.inbox', $channel->name);

        $this->assertSame([
            'conversation_id' => 11,
            'message_id' => 22,
            'direction' => 'out',
        ], $event->broadcastWith());
    }

    public function test_user_may_subscribe_to_own_tenant_channel(): void
    {
        $user = $this->actingAsClinicUser();

        $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-tenant.'.$user->tenant_id.'.inbox',
            'socket_id' => '1234.5678',
        ])->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_user_may_not_subscribe_to_another_tenant_channel(): void
    {
        $this->actingAsClinicUser();
        $other = Tenant::create([
            'name' => 'Klinik Lain',
            'slug' => 'klinik-lain',
            'phone' => '08110000001',
            'status' => 'active',
        ]);

        $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-tenant.'.$other->id.'.inbox',
            'socket_id' => '1234.5678',
        ])->assertForbidden();
    }

    public function test_channel_authorization_requires_an_authenticated_user(): void
    {
        $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-tenant.'.$this->createTenant()->id.'.inbox',
            'socket_id' => '1234.5678',
        ])->assertUnauthorized();
    }

    public function test_inbound_action_broadcasts_new_message_but_not_replayed_one(): void
    {
        $this->tenant = $this->createTenant();
        Event::fake([InboxMessageCreated::class]);

        $action = app(RecordInboundMessageAction::class);
        $message = $action->handle(
            $this->tenant->id,
            '081234567890',
            'Halo, masih buka?',
            ConversationChannel::Waha,
            'wamid-1',
        );

        Event::assertDispatched(InboxMessageCreated::class, fn (InboxMessageCreated $event): bool => $event->tenantId === $this->tenant->id
            && $event->conversationId === (int) $message->conversation_id
            && $event->messageId === (int) $message->id
            && $event->direction === MessageDirection::In);

        $replay = $action->handle(
            $this->tenant->id,
            '081234567890',
            'Halo, masih buka?',
            ConversationChannel::Waha,
            'wamid-1',
        );

        $this->assertSame($message->id, $replay->id);
        Event::assertDispatchedTimes(InboxMessageCreated::class, 1);
    }

    public function test_outbound_action_broadcasts_created_message(): void
    {
        $this->tenant = $this->createTenant();
        Event::fake([InboxMessageCreated::class]);

        $message = app(RecordOutboundMessageAction::class)->handle(
            $this->tenant->id,
            '081234567890',
            'Masih buka sampai jam lima.',
            ConversationChannel::Waha,
        );

        Event::assertDispatched(InboxMessageCreated::class, fn (InboxMessageCreated $event): bool => $event->tenantId === $this->tenant->id
            && $event->conversationId === (int) $message->conversation_id
            && $event->messageId === (int) $message->id
            && $event->direction === MessageDirection::Out);
    }
}
