<?php

namespace Tests\Feature\Inbox;

use App\Enums\ConversationChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Balasan dengan konteks: pesan yang dikutip dikirim sebagai context Cloud API
 * dan disimpan sebagai relasi di utasnya.
 */
class InboxReplyTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const PHONE_NUMBER_ID = '109876543210';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->actingAsClinicUser();

        config([
            'meta_whatsapp.access_token' => 'token-uji',
            'meta_whatsapp.api_version' => 'v21.0',
            'meta_whatsapp.base_url' => 'https://graph.test',
        ]);

        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            'phone_number_id' => self::PHONE_NUMBER_ID,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://graph.test/*' => Http::response(['messages' => [['id' => 'wamid.reply.1']]], 200),
        ]);
    }

    private function conversation(string $phone = '6281234567890'): Conversation
    {
        return Conversation::create([
            'tenant_id' => $this->tenant->id,
            'contact_phone' => $phone,
            'channel' => ConversationChannel::CloudApi,
            'last_message_at' => now(),
        ]);
    }

    private function inbound(Conversation $conversation, string $providerMessageId): Message
    {
        return $conversation->messages()->create([
            'direction' => MessageDirection::In,
            'body' => 'Halo, apakah besok buka?',
            'provider_message_id' => $providerMessageId,
            'status' => MessageStatus::Sent,
            'sent_at' => now()->subHour(),
        ]);
    }

    public function test_a_reply_sends_context_and_records_the_quoted_message(): void
    {
        $conversation = $this->conversation();
        $quoted = $this->inbound($conversation, 'wamid.in.1');

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/messages'), [
            'body' => 'Besok buka jam sembilan ya.',
            'reply_to_message_id' => $quoted->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.reply_to.id', $quoted->id)
            ->assertJsonPath('data.reply_to.body', 'Halo, apakah besok buka?')
            ->assertJsonPath('data.reply_to.direction', MessageDirection::In->value);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages')
            && $request['type'] === 'text'
            && $request['context']['message_id'] === 'wamid.in.1');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Out->value,
            'reply_to_message_id' => $quoted->id,
        ]);
    }

    public function test_the_thread_exposes_reply_context(): void
    {
        $conversation = $this->conversation();
        $quoted = $this->inbound($conversation, 'wamid.in.1');

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/messages'), [
            'body' => 'Besok buka jam sembilan ya.',
            'reply_to_message_id' => $quoted->id,
        ])->assertCreated();

        $this->getJson($this->tenantUrl('inbox/conversations/'.$conversation->id))
            ->assertOk()
            ->assertJsonPath('data.messages.0.reply_to.id', $quoted->id)
            ->assertJsonPath('data.messages.1.reply_to', null);
    }

    public function test_a_message_from_another_conversation_cannot_be_quoted(): void
    {
        $conversation = $this->conversation();
        $other = $this->conversation('6289999999999');
        $foreign = $other->messages()->create([
            'direction' => MessageDirection::In,
            'body' => 'Pesan utas lain',
            'provider_message_id' => 'wamid.other.1',
            'status' => MessageStatus::Sent,
            'sent_at' => now()->subHour(),
        ]);

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/messages'), [
            'body' => 'Membalas pesan utas lain.',
            'reply_to_message_id' => $foreign->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reply_to_message_id');

        Http::assertNothingSent();
    }
}
