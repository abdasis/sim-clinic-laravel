<?php

namespace Tests\Feature\Inbox;

use App\Enums\ClinicRole;
use App\Enums\ConversationChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * API kotak masuk: daftar utas, isi percakapan, balasan manual, tanda dibaca,
 * serta batas klinik dan peran.
 */
class InboxApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->actingAsClinicUser();
    }

    /** @param  array<string, mixed>  $attributes */
    private function conversation(array $attributes = []): Conversation
    {
        return Conversation::create([
            'tenant_id' => $this->tenant->id,
            'contact_phone' => '6281234567890',
            'contact_name' => 'Budi',
            'channel' => ConversationChannel::Waha,
            'last_message_at' => now(),
            ...$attributes,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function message(Conversation $conversation, array $attributes = []): Message
    {
        return $conversation->messages()->create([
            'direction' => MessageDirection::In,
            'body' => 'Halo',
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
            ...$attributes,
        ]);
    }

    public function test_conversations_are_listed_newest_first(): void
    {
        $older = $this->conversation(['contact_phone' => '628111111111', 'last_message_at' => now()->subHour()]);
        $newer = $this->conversation(['contact_phone' => '628222222222', 'last_message_at' => now()]);

        $this->getJson($this->tenantUrl('inbox/conversations'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_conversations_can_be_searched_by_name_or_phone(): void
    {
        $this->conversation(['contact_name' => 'Sari Wulandari']);
        $this->conversation(['contact_phone' => '628999999999', 'contact_name' => 'Budi']);

        $this->getJson($this->tenantUrl('inbox/conversations?search=Sari'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.contact_name', 'Sari Wulandari');
    }

    public function test_a_conversation_shows_its_messages_paginated(): void
    {
        $conversation = $this->conversation();
        $this->message($conversation, ['body' => 'Pertama', 'sent_at' => now()->subMinute()]);
        $this->message($conversation, ['body' => 'Kedua', 'sent_at' => now()]);

        $this->getJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'?per_page=1'))
            ->assertOk()
            ->assertJsonPath('data.messages.0.body', 'Kedua')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_a_reply_is_sent_through_waha_and_recorded(): void
    {
        $conversation = $this->conversation();

        WahaSetting::create(['base_url' => 'https://waha.test', 'api_key' => 'kunci']);
        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);

        Http::fake([
            'https://waha.test/api/sendText' => Http::response(['ok' => true], 201),
        ]);

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/messages'), [
            'body' => 'Halo Budi, facial mulai 150 ribu ya.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.direction', MessageDirection::Out->value)
            ->assertJsonPath('data.body', 'Halo Budi, facial mulai 150 ribu ya.');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/sendText')
            && $request['text'] === 'Halo Budi, facial mulai 150 ribu ya.');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Out->value,
            'body' => 'Halo Budi, facial mulai 150 ribu ya.',
        ]);
    }

    public function test_marking_a_conversation_read_clears_the_unread_count(): void
    {
        $conversation = $this->conversation(['unread_count' => 3]);

        $this->patchJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/read'))
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(0, $conversation->fresh()->unread_count);
    }

    public function test_another_clinics_conversation_is_not_found(): void
    {
        $other = $this->createTenant('klinik-tetangga');

        $foreign = Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $other->id,
            'contact_phone' => '628777777777',
            'channel' => ConversationChannel::Waha,
            'last_message_at' => now(),
        ]);

        // Kembalikan tenant aktif ke klinik yang login.
        app()->instance('tenant', $this->tenant);

        $this->getJson($this->tenantUrl('inbox/conversations/'.$foreign->id))->assertNotFound();

        $this->postJson($this->tenantUrl('inbox/conversations/'.$foreign->id.'/messages'), [
            'body' => 'Halo',
        ])->assertNotFound();
    }

    public function test_a_role_without_manage_permission_cannot_reply(): void
    {
        $conversation = $this->conversation();

        $this->actingAsClinicUser(ClinicRole::Doctor);

        $this->getJson($this->tenantUrl('inbox/conversations'))
            ->assertOk();

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/messages'), [
            'body' => 'Halo',
        ])->assertForbidden();
    }

    public function test_an_empty_reply_is_rejected(): void
    {
        $conversation = $this->conversation();

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/messages'), [
            'body' => '   ',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');
    }
}
