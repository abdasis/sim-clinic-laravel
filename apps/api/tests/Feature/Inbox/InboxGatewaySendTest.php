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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Balasan kotak masuk dikirim lewat gateway WhatsApp internal: teks memakai
 * endpoint pesan, lampiran memakai URL publik, dan penolakan gateway
 * menghapus berkas yang sudah tersimpan.
 */
class InboxGatewaySendTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const SESSION_ID = '11111111-1111-1111-1111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->actingAsClinicUser();

        config([
            'whatsapp_gateway.base_url' => 'https://gateway.test',
            'app.url' => 'https://app.test',
        ]);

        // URL disk diuji apa adanya: gateway hanya menerima alamat publik.
        Storage::fake('public', ['url' => 'https://app.test/storage']);

        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            'gateway_tenant_key' => 'tenant-uji',
            'gateway_api_key' => 'api-uji',
            'gateway_session_id' => self::SESSION_ID,
        ]);
    }

    private function conversation(): Conversation
    {
        return Conversation::create([
            'tenant_id' => $this->tenant->id,
            'contact_phone' => '6281234567890',
            'channel' => ConversationChannel::Gateway,
            'last_message_at' => now(),
        ]);
    }

    private function inbound(Conversation $conversation, int $hoursAgo = 1): Message
    {
        return $conversation->messages()->create([
            'direction' => MessageDirection::In,
            'body' => 'Halo, apakah besok buka?',
            'provider_message_id' => 'wamid.in.1',
            'status' => MessageStatus::Sent,
            'sent_at' => now()->subHours($hoursAgo),
        ]);
    }

    private function fakeGateway(string $messageId = 'uuid-pesan'): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'gateway.test/*' => Http::response([
                'success' => true,
                'data' => ['messageId' => $messageId, 'status' => 'QUEUED'],
            ], 202),
        ]);
    }

    public function test_text_reply_sends_to_gateway_and_stores_provider_id(): void
    {
        $conversation = $this->conversation();
        $this->inbound($conversation);
        $this->fakeGateway('uuid-pesan');

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/messages'), [
            'body' => 'Halo',
        ])
            ->assertCreated()
            ->assertJsonPath('data.provider_message_id', 'uuid-pesan');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://gateway.test/api/v1/messages/send'
            && $request->hasHeader('x-tenant-key', 'tenant-uji')
            && $request->hasHeader('X-API-Key', 'api-uji')
            && str_starts_with((string) $request->header('Idempotency-Key')[0], 'inbox-')
            && $request['sessionId'] === self::SESSION_ID
            && $request['to'] === '6281234567890'
            && $request['text'] === 'Halo');

        $this->assertDatabaseHas('messages', [
            'body' => 'Halo',
            'provider_message_id' => 'uuid-pesan',
        ]);
    }

    public function test_a_quoted_reply_is_stored_but_not_sent_to_the_gateway(): void
    {
        $conversation = $this->conversation();
        $quoted = $this->inbound($conversation);
        $this->fakeGateway('uuid-kutipan');

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/messages'), [
            'body' => 'Besok buka jam sembilan ya.',
            'reply_to_message_id' => $quoted->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.reply_to.id', $quoted->id);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages/send')
            && ! isset($request['context'])
            && $request['text'] === 'Besok buka jam sembilan ya.');

        $this->assertDatabaseHas('messages', ['reply_to_message_id' => $quoted->id]);
    }

    public function test_a_message_outside_the_old_24_hour_window_is_still_replied(): void
    {
        $conversation = $this->conversation();
        $this->inbound($conversation, 25);
        $this->fakeGateway('uuid-luar-jendela');

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/messages'), [
            'body' => 'Pesan baru.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.provider_message_id', 'uuid-luar-jendela');
    }

    public function test_image_reply_uses_public_url_and_cleans_file_on_failure(): void
    {
        $conversation = $this->conversation();

        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([
            'success' => false,
            'error' => ['code' => 'MEDIA_URL_BLOCKED'],
        ], 422)]);

        $this->post($this->tenantUrl('inbox/conversations/'.$conversation->id.'/media'), [
            'type' => 'image',
            'file' => UploadedFile::fake()->image('foto.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertEmpty(Storage::disk('public')->allFiles('inbox-media'));
        $this->assertDatabaseMissing('messages', ['media_type' => 'image']);
    }

    public function test_media_payload_sends_public_url_not_local_path(): void
    {
        $conversation = $this->conversation();
        $this->fakeGateway('uuid-media');

        $this->post($this->tenantUrl('inbox/conversations/'.$conversation->id.'/media'), [
            'type' => 'image',
            'caption' => 'Poster promo',
            'file' => UploadedFile::fake()->image('foto.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.provider_message_id', 'uuid-media');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages/image')
            && str_starts_with((string) $request['url'], 'https://app.test/storage/inbox-media/')
            && $request['caption'] === 'Poster promo'
            && ! str_contains((string) $request['url'], storage_path()));
    }

    public function test_audio_reply_uses_audio_endpoint(): void
    {
        $conversation = $this->conversation();
        $this->fakeGateway('uuid-audio');

        $this->post($this->tenantUrl('inbox/conversations/'.$conversation->id.'/media'), [
            'type' => 'audio',
            'file' => UploadedFile::fake()->create('catatan.mp3', 100, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.provider_message_id', 'uuid-audio');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages/audio')
            && str_starts_with((string) $request['url'], 'https://app.test/storage/inbox-media/')
            && $request['voiceNote'] === false);
    }

    public function test_document_reply_uses_document_endpoint_with_filename(): void
    {
        $conversation = $this->conversation();
        $this->fakeGateway('uuid-dokumen');

        $this->post($this->tenantUrl('inbox/conversations/'.$conversation->id.'/media'), [
            'type' => 'document',
            'file' => UploadedFile::fake()->create('hasil-lab.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'hasil-lab.pdf');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages/document')
            && str_starts_with((string) $request['url'], 'https://app.test/storage/inbox-media/')
            && $request['filename'] === 'hasil-lab.pdf');
    }

    public function test_contact_is_sent_as_text(): void
    {
        $conversation = $this->conversation();
        $this->fakeGateway('uuid-kontak');

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/contact'), [
            'name' => 'Sari Wulandari',
            'phone' => '6289876543210',
        ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Sari Wulandari: +6289876543210');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages/send')
            && $request['text'] === 'Sari Wulandari: +6289876543210');
    }

    /**
     * Jatah harian habis: balasan ditolak dengan alasan yang bisa dibaca,
     * bukan "gagal terkirim" yang membuat admin mencoba terus.
     */
    public function test_reply_is_refused_with_a_clear_message_once_the_daily_quota_is_spent(): void
    {
        $conversation = $this->conversation();
        config(['broadcast.daily_limit' => 1]);

        $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'body' => 'Sudah terkirim sebelumnya',
            'status' => MessageStatus::Sent,
            'sent_at' => now()->subHour(),
        ]);

        Http::preventStrayRequests();
        Http::fake();

        $this->postJson($this->tenantUrl('inbox/conversations/'.$conversation->id.'/messages'), [
            'body' => 'Halo',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.body.0', __('inbox.daily_limit_reached'));

        Http::assertNothingSent();
    }

    public function test_reaction_route_no_longer_exists(): void
    {
        $conversation = $this->conversation();
        $message = $this->inbound($conversation);
        Http::fake();

        $this->postJson($this->tenantUrl(
            'inbox/conversations/'.$conversation->id.'/messages/'.$message->id.'/reaction',
        ), ['emoji' => '👍'])->assertNotFound();

        Http::assertNothingSent();
    }
}
