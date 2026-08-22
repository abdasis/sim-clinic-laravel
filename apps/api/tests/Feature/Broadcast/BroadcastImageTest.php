<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\Patient;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
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
 * sendiri, pesannya berangkat sebagai keterangan gambar (bukan kiriman
 * kedua), dan poster yang hilang dari disk tidak menggagalkan pesannya.
 */
class BroadcastImageTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function wahaConnected(): void
    {
        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);
        WahaSetting::create(['base_url' => 'https://waha.test', 'api_key' => 'kunci']);

        Http::fake([
            'waha.test/api/sessions/*' => Http::response(['status' => 'WORKING'], 200),
            'waha.test/*' => Http::response(['id' => 'true_628@c.us'], 201),
        ]);
    }

    private function createWithImage(?UploadedFile $image = null): Broadcast
    {
        Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'whatsapp' => '081111111111',
            'name' => 'Ani',
        ]);

        $id = $this->post($this->tenantUrl('broadcasts'), [
            'title' => 'Promo Agustus',
            'message' => 'Halo {nama}, ada promo bulan ini.',
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

    /** Tanpa gambar, jalur lama tetap dipakai. */
    public function test_a_broadcast_without_an_image_still_sends_plain_text(): void
    {
        $this->actingAsClinicUser();
        $this->wahaConnected();

        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'whatsapp' => '081111111111']);

        $id = $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Tanpa poster', 'message' => 'Halo', 'audience' => 'all',
        ])->assertCreated()->json('data.id');

        $broadcast = Broadcast::find($id);
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        (new SendBroadcastRecipientJob($broadcast->recipients()->first()->id, $this->tenant->id))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/sendText'));
    }

    /**
     * Pesannya jadi keterangan gambar, bukan kiriman kedua: dua kiriman
     * berurutan sampai sebagai dua notifikasi terpisah.
     */
    public function test_the_message_travels_as_the_image_caption(): void
    {
        $this->actingAsClinicUser();
        $this->wahaConnected();

        $broadcast = $this->createWithImage();
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        (new SendBroadcastRecipientJob($recipient->id, $this->tenant->id))->handle();

        $this->assertSame('sent', $recipient->fresh()->status->value);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/sendImage')) {
                return false;
            }

            return $request['caption'] === 'Halo Ani, ada promo bulan ini.'
                && $request['chatId'] === '6281111111111@c.us'
                && ! empty($request['file']['data'])
                && $request['file']['mimetype'] === 'image/jpeg';
        });

        // Hanya satu kiriman isi; tidak ada teks susulan.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/sendText'));
    }

    /**
     * Poster yang hilang dari disk tidak boleh menggagalkan kabarnya —
     * kehilangan gambar lebih ringan daripada pasien tidak menerima apa pun.
     */
    public function test_a_missing_poster_falls_back_to_plain_text(): void
    {
        $this->actingAsClinicUser();
        $this->wahaConnected();

        $broadcast = $this->createWithImage();
        Storage::disk('public')->delete($broadcast->image_path);

        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        (new SendBroadcastRecipientJob($recipient->id, $this->tenant->id))->handle();

        $this->assertSame('sent', $recipient->fresh()->status->value);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/sendText'));
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        $this->actingAsClinicUser();

        $this->post($this->tenantUrl('broadcasts'), [
            'title' => 'Promo',
            'message' => 'Halo',
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
            'message' => 'Halo',
            'audience' => 'all',
            'image' => UploadedFile::fake()->image('besar.jpg')->size(3000),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
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
