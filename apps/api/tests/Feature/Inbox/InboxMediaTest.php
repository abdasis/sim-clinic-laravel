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
 * Lampiran lewat Cloud API Meta: unggah dulu, kirim dengan id medianya,
 * simpan berkasnya, dan bersihkan lagi bila penyedia menolak.
 */
class InboxMediaTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const PHONE_NUMBER_ID = '109876543210';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->actingAsClinicUser();
        Storage::fake('public');

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

    private function fakeProvider(string $messageId = 'wamid.media.1'): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.test/v21.0/*/media' => Http::response(['id' => 'media-1'], 200),
            'https://graph.test/v21.0/*/messages' => Http::response(['messages' => [['id' => $messageId]]], 200),
        ]);
    }

    public function test_an_image_is_uploaded_then_sent_and_recorded(): void
    {
        $conversation = $this->conversation();
        $this->inbound($conversation, 2);
        $this->fakeProvider();

        $this->post($this->tenantUrl('inbox/conversations/'.$conversation->id.'/media'), [
            'type' => 'image',
            'caption' => 'Poster promo',
            'file' => UploadedFile::fake()->create('promo.jpg', 200, 'image/jpeg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.media_type', 'image')
            ->assertJsonPath('data.media_name', 'promo.jpg')
            ->assertJsonPath('data.body', 'Poster promo')
            ->assertJsonPath('data.provider_message_id', 'wamid.media.1');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/media')
            && $request->hasFile('file'));

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages')
            && $request['type'] === 'image'
            && $request['image']['id'] === 'media-1'
            && $request['image']['caption'] === 'Poster promo');

        $message = Message::query()->latest('id')->firstOrFail();

        $this->assertSame('image', $message->media_type);
        $this->assertNotNull($message->media_path);
        Storage::disk('public')->assertExists($message->media_path);
    }

    public function test_a_document_is_sent_with_its_filename_and_no_caption(): void
    {
        $conversation = $this->conversation();
        $this->inbound($conversation, 1);
        $this->fakeProvider('wamid.media.2');

        $this->post($this->tenantUrl('inbox/conversations/'.$conversation->id.'/media'), [
            'type' => 'document',
            'file' => UploadedFile::fake()->create('hasil-lab.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.media_type', 'document')
            ->assertJsonPath('data.body', 'hasil-lab.pdf');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages')
            && $request['type'] === 'document'
            && $request['document']['filename'] === 'hasil-lab.pdf'
            && ! isset($request['document']['caption']));
    }

    public function test_a_file_that_does_not_match_the_declared_type_is_rejected(): void
    {
        $conversation = $this->conversation();
        $this->inbound($conversation, 1);
        Http::fake();

        $this->post($this->tenantUrl('inbox/conversations/'.$conversation->id.'/media'), [
            'type' => 'image',
            'file' => UploadedFile::fake()->create('dokumen.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        Http::assertNothingSent();
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_attachment_outside_the_24_hour_window_is_rejected(): void
    {
        $conversation = $this->conversation();
        $this->inbound($conversation, 25);
        Http::fake();

        $this->post($this->tenantUrl('inbox/conversations/'.$conversation->id.'/media'), [
            'type' => 'image',
            'file' => UploadedFile::fake()->create('promo.jpg', 20, 'image/jpeg'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file')
            ->assertJsonPath('errors.file.0', __('inbox.window_expired'));

        Http::assertNothingSent();
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_stored_file_is_removed_when_the_provider_rejects_the_upload(): void
    {
        $conversation = $this->conversation();
        $this->inbound($conversation, 1);

        Http::preventStrayRequests();
        Http::fake([
            'https://graph.test/v21.0/*/media' => Http::response(['error' => ['message' => 'Media tidak sah']], 400),
        ]);

        $this->post($this->tenantUrl('inbox/conversations/'.$conversation->id.'/media'), [
            'type' => 'image',
            'file' => UploadedFile::fake()->create('promo.jpg', 20, 'image/jpeg'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseMissing('messages', ['media_type' => 'image']);
    }
}
