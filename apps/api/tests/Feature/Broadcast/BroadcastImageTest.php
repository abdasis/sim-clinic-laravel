<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\Patient;
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
 * sendiri, tautannya ikut sebagai header template, dan template tanpa header
 * tidak mengirim komponen header sama sekali.
 */
class BroadcastImageTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function cloudConnected(): void
    {
        config([
            'meta_whatsapp.access_token' => 'token-uji',
            'meta_whatsapp.api_version' => 'v21.0',
            'meta_whatsapp.base_url' => 'https://graph.test',
        ]);

        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'phone_number_id' => '109876543210',
        ]);

        Http::fake([
            'graph.test/*' => Http::response(['messages' => [['id' => 'wamid.ok']]], 200),
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
                'name' => 'promo_bergambar',
                'language' => 'id',
                'body' => 'Halo {{1}}, ada promo bulan ini.',
                'header_type' => 'IMAGE',
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

    /** Tanpa gambar header, template tetap terkirim sebagai template biasa. */
    public function test_a_template_without_an_image_still_sends(): void
    {
        $this->actingAsClinicUser();
        $this->cloudConnected();

        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'whatsapp' => '081111111111']);

        $id = $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Tanpa poster',
            'template' => [
                'name' => 'promo_teks',
                'language' => 'id',
                'body' => 'Halo {{1}}',
                'params' => ['nama'],
            ],
            'audience' => 'all',
        ])->assertCreated()->json('data.id');

        $broadcast = Broadcast::find($id);
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        (new SendBroadcastRecipientJob($broadcast->recipients()->first()->id, $this->tenant->id))->handle();

        $this->assertSame('sent', $broadcast->recipients()->first()->fresh()->status->value);

        Http::assertSent(fn ($request) => $request['type'] === 'template'
            && collect($request['template']['components'] ?? [])
                ->pluck('type')
                ->doesntContain('header'));
    }

    /**
     * Posternya jadi komponen header template, bukan kiriman terpisah: dua
     * kiriman berurutan sampai sebagai dua notifikasi terpisah.
     */
    public function test_the_poster_travels_as_the_template_header(): void
    {
        $this->actingAsClinicUser();
        $this->cloudConnected();

        $broadcast = $this->createWithImage();
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        (new SendBroadcastRecipientJob($recipient->id, $this->tenant->id))->handle();

        $this->assertSame('sent', $recipient->fresh()->status->value);

        Http::assertSent(function ($request) use ($broadcast) {
            if ($request['type'] !== 'template') {
                return false;
            }

            $components = $request['template']['components'] ?? [];
            $header = collect($components)->firstWhere('type', 'header');

            return $request['to'] === '6281111111111'
                && $request['template']['name'] === 'promo_bergambar'
                && ($header['parameters'][0]['image']['link'] ?? null) === $broadcast->image_url;
        });
    }

    /**
     * Template tanpa header gambar tidak mengirim komponen header kosong:
     * Meta menolaknya sebagai parameter yang tidak dikenal.
     */
    public function test_a_template_without_an_image_header_sends_no_header_component(): void
    {
        $this->actingAsClinicUser();
        $this->cloudConnected();

        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'whatsapp' => '081111111111']);

        $id = $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Tanpa poster',
            'template' => [
                'name' => 'promo_teks',
                'language' => 'id',
                'body' => 'Halo {{1}}',
                'params' => ['nama'],
            ],
            'audience' => 'all',
        ])->assertCreated()->json('data.id');

        $broadcast = Broadcast::find($id);
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        (new SendBroadcastRecipientJob($recipient->id, $this->tenant->id))->handle();

        Http::assertSent(fn ($request) => collect($request['template']['components'] ?? [])
            ->pluck('type')
            ->doesntContain('header'));
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        $this->actingAsClinicUser();

        $this->post($this->tenantUrl('broadcasts'), [
            'title' => 'Promo',
            'template' => [
                'name' => 'promo_teks',
                'language' => 'id',
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
                'name' => 'promo_teks',
                'language' => 'id',
                'body' => 'Halo',
                'params' => [],
            ],
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
