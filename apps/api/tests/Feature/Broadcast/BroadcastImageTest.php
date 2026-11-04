<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Broadcast berisi poster.
 *
 * Promo klinik hampir selalu berupa gambar, jadi satu broadcast boleh
 * membawanya. Yang dijaga: berkasnya mendarat di folder milik kliniknya
 * sendiri, dan gambarnya terkirim sebagai lampiran dengan pesan sebagai
 * caption — bukan dua kiriman terpisah yang sampai sebagai dua notifikasi.
 */
class BroadcastImageTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function gatewayConnected(): void
    {
        $this->configureWhatsappGateway();

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => ['messageId' => 'uuid-ok', 'status' => 'QUEUED'],
            ], 202),
        ]);
    }

    /**
     * @param  array<string, mixed>  $templateOverrides
     */
    private function createWithImage(?UploadedFile $image = null, array $templateOverrides = []): Broadcast
    {
        Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'whatsapp' => '081111111111',
            'name' => 'Ani',
        ]);

        $id = $this->post($this->tenantUrl('broadcasts'), [
            'title' => 'Promo Agustus',
            'template' => array_replace([
                'body' => 'Halo {{1}}, ada promo bulan ini.',
                'params' => ['nama'],
            ], $templateOverrides),
            'audience' => 'all',
            'image' => $image ?? UploadedFile::fake()->image('poster.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        return Broadcast::find($id);
    }

    public function test_a_broadcast_may_carry_a_poster(): void
    {
        $this->actingAsClinicUser();

        $broadcast = $this->createWithImage();

        $this->assertNotNull($broadcast->image_path);
        Storage::disk('public')->assertExists($broadcast->image_path);
    }

    /** Berkasnya mendarat di folder milik kliniknya sendiri. */
    public function test_the_poster_lands_in_a_folder_scoped_to_the_clinic(): void
    {
        $this->actingAsClinicUser();

        $broadcast = $this->createWithImage();

        $this->assertStringStartsWith(
            'broadcast-images/'.$this->tenant->id.'/',
            $broadcast->image_path,
        );
    }

    public function test_the_image_url_reaches_the_client(): void
    {
        $this->actingAsClinicUser();
        $broadcast = $this->createWithImage();

        $this->getJson($this->tenantUrl("broadcasts/{$broadcast->id}"))
            ->assertOk()
            ->assertJsonPath('data.image_url', Storage::disk('public')->url($broadcast->image_path));
    }

    /** Tanpa poster, pesannya tetap terkirim sebagai teks biasa. */
    public function test_a_broadcast_without_an_image_sends_as_text(): void
    {
        $this->actingAsClinicUser();
        $this->gatewayConnected();

        Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'whatsapp' => '081111111111',
            'name' => 'Ani',
        ]);

        $id = $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Tanpa poster',
            'template' => [
                'body' => 'Halo {{1}}',
                'params' => ['nama'],
            ],
            'audience' => 'all',
        ])->assertCreated()->json('data.id');

        $broadcast = Broadcast::find($id);
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        (new SendBroadcastRecipientJob($recipient->id, $this->tenant->id))->handle();

        $this->assertSame('sent', $recipient->fresh()->status->value);

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/messages/send'
            && $request['text'] === 'Halo Ani'
            && ! isset($request['caption']));
    }

    /**
     * Posternya jadi lampiran pesan yang sama, bukan kiriman terpisah: dua
     * kiriman berurutan sampai sebagai dua notifikasi terpisah.
     */
    public function test_the_poster_travels_as_the_image_with_the_message_as_caption(): void
    {
        $this->actingAsClinicUser();
        $this->gatewayConnected();

        $broadcast = $this->createWithImage();
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        (new SendBroadcastRecipientJob($recipient->id, $this->tenant->id))->handle();

        $this->assertSame('sent', $recipient->fresh()->status->value);

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/messages/image'
            && $request['to'] === '6281111111111'
            && $request['url'] === $broadcast->image_url
            && $request['caption'] === 'Halo Ani, ada promo bulan ini.');
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        $this->actingAsClinicUser();

        $this->post($this->tenantUrl('broadcasts'), [
            'title' => 'Promo',
            'template' => [
                'body' => 'Halo',
                'params' => [],
            ],
            'audience' => 'all',
            'image' => UploadedFile::fake()->create('brosur.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_an_oversized_poster_is_rejected(): void
    {
        $this->actingAsClinicUser();

        $this->post($this->tenantUrl('broadcasts'), [
            'title' => 'Promo',
            'template' => [
                'body' => 'Halo',
                'params' => [],
            ],
            'audience' => 'all',
            'image' => UploadedFile::fake()->image('besar.jpg')->size(3000),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    /** Poster memakan ruang layar, jadi pesannya dibatasi lebih pendek. */
    public function test_a_long_body_is_rejected_when_a_poster_is_attached(): void
    {
        $this->actingAsClinicUser();

        $this->post($this->tenantUrl('broadcasts'), [
            'title' => 'Promo',
            'template' => [
                'body' => str_repeat('a', 1100),
                'params' => [],
            ],
            'audience' => 'all',
            'image' => UploadedFile::fake()->image('poster.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('template.body');
    }

    /** Broadcast yang dihapus tidak meninggalkan berkas yatim di disk. */
    public function test_deleting_a_broadcast_removes_its_poster(): void
    {
        $this->actingAsClinicUser();
        $broadcast = $this->createWithImage();
        $path = $broadcast->image_path;

        $this->deleteJson($this->tenantUrl("broadcasts/{$broadcast->id}"))->assertOk();

        Storage::disk('public')->assertMissing($path);
    }
}
