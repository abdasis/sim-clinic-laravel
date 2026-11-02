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
 * Reaksi emoji pada pesan: dikirim lewat Cloud API lalu disimpan, dan emoji
 * kosong mencabutnya kembali.
 */
class InboxReactionTest extends TestCase
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
            'https://graph.test/*' => Http::response(['messages' => [['id' => 'wamid.reaction.1']]], 200),
        ]);
    }

    private function outboundMessage(string $conversationPhone = '6281234567890'): Message
    {
        $conversation = Conversation::query()->firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'contact_phone' => $conversationPhone],
            ['channel' => ConversationChannel::CloudApi, 'last_message_at' => now()],
        );

        return $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'body' => 'Sampai jumpa besok.',
            'provider_message_id' => 'wamid.out.1',
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function test_a_reaction_is_sent_and_stored(): void
    {
        $message = $this->outboundMessage();

        $this->postJson($this->tenantUrl(
            'inbox/conversations/'.$message->conversation_id.'/messages/'.$message->id.'/reaction'
        ), ['emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('data.reaction', '👍');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages')
            && $request['type'] === 'reaction'
            && $request['reaction']['message_id'] === 'wamid.out.1'
            && $request['reaction']['emoji'] === '👍');

        $this->assertDatabaseHas('messages', ['id' => $message->id, 'reaction' => '👍']);
    }

    public function test_an_empty_emoji_removes_the_reaction(): void
    {
        $message = $this->outboundMessage();
        $message->update(['reaction' => '👍']);

        $this->postJson($this->tenantUrl(
            'inbox/conversations/'.$message->conversation_id.'/messages/'.$message->id.'/reaction'
        ), ['emoji' => ''])
            ->assertOk()
            ->assertJsonPath('data.reaction', null);

        Http::assertSent(fn (Request $request) => $request['type'] === 'reaction'
            && $request['reaction']['emoji'] === '');

        $this->assertDatabaseHas('messages', ['id' => $message->id, 'reaction' => null]);
    }

    public function test_a_message_from_another_conversation_is_not_found(): void
    {
        $message = $this->outboundMessage();
        $other = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'contact_phone' => '6289999999999',
            'channel' => ConversationChannel::CloudApi,
            'last_message_at' => now(),
        ]);

        $this->postJson($this->tenantUrl(
            'inbox/conversations/'.$other->id.'/messages/'.$message->id.'/reaction'
        ), ['emoji' => '👍'])
            ->assertNotFound();

        Http::assertNothingSent();
    }
}
