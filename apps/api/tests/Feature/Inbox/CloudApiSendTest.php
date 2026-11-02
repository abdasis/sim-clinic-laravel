<?php

namespace Tests\Feature\Inbox;

use App\Enums\ConversationChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Balasan Cloud API Meta: jalurnya, id pesan yang dikembalikan, dan jendela
 * 24 jam yang membatasi balasan bebas.
 */
class CloudApiSendTest extends TestCase
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
            'https://graph.test/*' => Http::response(['messages' => [['id' => 'wamid.out.1']]], 200),
        ]);
    }

    private function conversation(): Conversation
    {
        return Conversation::create([
            'tenant_id' => $this->tenant->id,
            'contact_phone' => '6281234567890',
            'channel' => ConversationChannel::CloudApi,
            'last_message_at' => now(),
        ]);
    }

    private function inbound(Conversation $conversation, int $hoursAgo): void
    {
        $conversation->messages()->create([
            'direction' => MessageDirection::In,
            'body' => 'Halo',
            'status' => MessageStatus::Sent,
            'sent_at' => now()->subHours($hoursAgo),
        ]);
    }

    public function test_a_reply_inside_the_24_hour_window_is_sent_and_recorded(): void
    {
        $conversation = $this->conversation();
        $this->inbound($conversation, 2);

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/messages'), [
            'body' => 'Halo, masih ada jadwal besok ya.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.provider_message_id', 'wamid.out.1')
            ->assertJsonPath('data.direction', MessageDirection::Out->value);

        Http::assertSent(fn (Request $request) => str_starts_with(
            $request->url(),
            'https://graph.test/v21.0/'.self::PHONE_NUMBER_ID.'/messages',
        )
            && $request['to'] === '6281234567890'
            && $request['text']['body'] === 'Halo, masih ada jadwal besok ya.');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Out->value,
            'provider_message_id' => 'wamid.out.1',
        ]);
    }

    public function test_a_reply_outside_the_24_hour_window_is_rejected(): void
    {
        $conversation = $this->conversation();
        $this->inbound($conversation, 25);

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/messages'), [
            'body' => 'Halo, masih ada jadwal besok ya.',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body')
            ->assertJsonPath('errors.body.0', __('inbox.window_expired'));

        Http::assertNothingSent();
    }

    public function test_a_reply_without_any_inbound_message_is_rejected(): void
    {
        $conversation = $this->conversation();

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/messages'), [
            'body' => 'Halo',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        Http::assertNothingSent();
    }
}
