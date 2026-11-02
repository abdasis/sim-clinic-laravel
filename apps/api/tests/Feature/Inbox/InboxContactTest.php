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
 * Kartu kontak lewat Cloud API Meta, plus penjagaan bentuk nomornya.
 */
class InboxContactTest extends TestCase
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
            'https://graph.test/*' => Http::response(['messages' => [['id' => 'wamid.contact.1']]], 200),
        ]);
    }

    private function conversation(): Conversation
    {
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'contact_phone' => '6281234567890',
            'channel' => ConversationChannel::CloudApi,
            'last_message_at' => now(),
        ]);

        $conversation->messages()->create([
            'direction' => MessageDirection::In,
            'body' => 'Halo',
            'status' => MessageStatus::Sent,
            'sent_at' => now()->subHour(),
        ]);

        return $conversation;
    }

    public function test_a_contact_is_sent_as_a_contact_card_and_recorded(): void
    {
        $conversation = $this->conversation();

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/contact'), [
            'name' => 'Sari Wulandari',
            'phone' => '6289876543210',
        ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Sari Wulandari: +6289876543210')
            ->assertJsonPath('data.direction', MessageDirection::Out->value)
            ->assertJsonPath('data.provider_message_id', 'wamid.contact.1');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages')
            && $request['type'] === 'contacts'
            && $request['contacts'][0]['name']['formatted_name'] === 'Sari Wulandari'
            && $request['contacts'][0]['phones'][0]['phone'] === '6289876543210');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Out->value,
            'body' => 'Sari Wulandari: +6289876543210',
        ]);
    }

    public function test_an_invalid_phone_number_is_rejected(): void
    {
        $conversation = $this->conversation();

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/contact'), [
            'name' => 'Sari',
            'phone' => '0812',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        Http::assertNothingSent();
    }
}
