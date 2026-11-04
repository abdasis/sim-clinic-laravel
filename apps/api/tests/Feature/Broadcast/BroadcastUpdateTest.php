<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Broadcast yang belum mulai dikirim masih boleh disunting: judul, penerima,
 * template, sampai posternya. Begitu pengiriman berjalan, isinya dikunci —
 * mengubah penerima di tengah antrean berarti separuh pasien menerima versi
 * lama dan separuh versi baru.
 */
class BroadcastUpdateTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @param  array<int, string>  $params
     * @return array<string, mixed>
     */
    private function template(string $body = 'Halo {{1}}', array $params = ['nama'], ?string $headerType = null): array
    {
        return array_filter([
            'name' => 'promo_uji',
            'language' => 'id',
            'body' => $body,
            'header_type' => $headerType,
            'params' => $params,
        ], fn ($value) => $value !== null);
    }

    private function patient(string $name, string $phone): Patient
    {
        return Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'whatsapp' => $phone,
            'whatsapp_opt_in' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createBroadcast(array $overrides = [], ?UploadedFile $image = null): Broadcast
    {
        $payload = array_replace([
            'title' => 'Promo Awal',
            'template' => $this->template(),
            'audience' => 'all',
        ], $overrides);

        if ($image !== null) {
            $payload['image'] = $image;

            $id = $this->post($this->tenantUrl('broadcasts'), $payload, ['Accept' => 'application/json'])
                ->assertCreated()->json('data.id');

            return Broadcast::findOrFail($id);
        }

        $id = $this->postJson($this->tenantUrl('broadcasts'), $payload)
            ->assertCreated()->json('data.id');

        return Broadcast::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Promo Diperbarui',
            'template' => $this->template(),
            'audience' => 'all',
        ], $overrides);
    }

    public function test_editing_rebuilds_title_audience_template_and_recipients(): void
    {
        $this->actingAsClinicUser();

        $ani = $this->patient('Ani', '081111111111');
        $budi = $this->patient('Budi', '082222222222');

        $broadcast = $this->createBroadcast([
            'audience' => 'selected',
            'audience_params' => ['patient_ids' => [$ani->id]],
        ]);

        $this->putJson($this->tenantUrl("broadcasts/{$broadcast->id}"), [
            'title' => 'Promo Baru',
            'audience' => 'selected',
            'audience_params' => ['patient_ids' => [$budi->id]],
            'template' => $this->template('Hai {{1}} dari {{2}}', ['nama', 'klinik'], 'TEXT'),
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Promo Baru')
            ->assertJsonPath('data.recipients_total', 1)
            ->assertJsonPath('meta.message', __('broadcast.edit_saved'));

        $broadcast->refresh();
        $recipient = $broadcast->recipients()->first();

        $this->assertSame(1, $broadcast->recipients()->count());
        $this->assertSame($budi->id, $recipient->patient_id);
        // Snapshot kampanye tidak mengenal satu pasien, jadi hanya bagian
        // klinik yang terisi; pesan per penerima tetap utuh.
        $this->assertStringContainsString('dari '.$this->tenant->name, $broadcast->message);
        $this->assertSame('Hai Budi dari '.$this->tenant->name, $recipient->message);
    }

    public function test_editing_is_rejected_once_the_sending_has_started(): void
    {
        $this->actingAsClinicUser();

        $broadcast = $this->createBroadcast();

        foreach ([
            BroadcastStatus::Sending,
            BroadcastStatus::Paused,
            BroadcastStatus::Done,
            BroadcastStatus::Failed,
            BroadcastStatus::Cancelled,
        ] as $status) {
            $broadcast->update(['status' => $status]);

            $this->putJson($this->tenantUrl("broadcasts/{$broadcast->id}"), $this->updatePayload())
                ->assertStatus(422)
                ->assertJsonPath('message', __('broadcast.edit_locked'));
        }

        $this->assertSame('Promo Awal', $broadcast->fresh()->title);
    }

    /** Status yang tertinggal tetap ditolak bila ada penerima yang sudah diproses. */
    public function test_editing_is_rejected_when_a_recipient_is_no_longer_pending(): void
    {
        $this->actingAsClinicUser();
        $this->patient('Ani', '081111111111');

        $broadcast = $this->createBroadcast();
        $broadcast->recipients()->update(['status' => 'sent']);

        $this->putJson($this->tenantUrl("broadcasts/{$broadcast->id}"), $this->updatePayload())
            ->assertStatus(422)
            ->assertJsonPath('message', __('broadcast.edit_locked'));
    }

    public function test_a_poster_is_kept_when_no_new_image_is_sent(): void
    {
        $this->actingAsClinicUser();

        $broadcast = $this->createBroadcast(
            ['template' => $this->template('Halo {{1}}', ['nama'], 'IMAGE')],
            UploadedFile::fake()->image('poster.jpg'),
        );

        $path = $broadcast->image_path;

        $this->putJson($this->tenantUrl("broadcasts/{$broadcast->id}"), [
            'title' => 'Promo Tetap Bergambar',
            'audience' => 'all',
            'template' => $this->template('Halo {{1}}', ['nama'], 'IMAGE'),
        ])
            ->assertOk()
            ->assertJsonPath('data.image_url', Storage::disk('public')->url($path));

        $this->assertSame($path, $broadcast->fresh()->image_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_new_poster_replaces_the_old_file(): void
    {
        $this->actingAsClinicUser();

        $broadcast = $this->createBroadcast(
            ['template' => $this->template('Halo {{1}}', ['nama'], 'IMAGE')],
            UploadedFile::fake()->image('poster-lama.jpg'),
        );

        $old = $broadcast->image_path;

        // Multipart dengan _method: unggahan berkas tidak bisa lewat JSON.
        $this->post($this->tenantUrl("broadcasts/{$broadcast->id}"), [
            '_method' => 'PUT',
            'title' => 'Promo dengan Poster Baru',
            'audience' => 'all',
            'template' => $this->template('Halo {{1}}', ['nama'], 'IMAGE'),
            'image' => UploadedFile::fake()->image('poster-baru.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $new = $broadcast->fresh()->image_path;

        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
    }

    public function test_remove_image_deletes_the_stored_poster(): void
    {
        $this->actingAsClinicUser();

        $broadcast = $this->createBroadcast(
            ['template' => $this->template('Halo {{1}}', ['nama'], 'IMAGE')],
            UploadedFile::fake()->image('poster.jpg'),
        );

        $path = $broadcast->image_path;

        $this->putJson($this->tenantUrl("broadcasts/{$broadcast->id}"), [
            'title' => 'Promo Tanpa Poster',
            'audience' => 'all',
            'template' => $this->template(),
            'remove_image' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.image_url', null);

        $this->assertNull($broadcast->fresh()->image_path);
        Storage::disk('public')->assertMissing($path);
    }

    /**
     * Poster yang masih menempel ikut menentukan batas panjang pesan, walau
     * tidak ada berkas baru yang diunggah.
     */
    public function test_a_stored_poster_shortens_the_body_limit_on_edit(): void
    {
        $this->actingAsClinicUser();

        $broadcast = $this->createBroadcast(
            ['template' => $this->template('Halo {{1}}', ['nama'])],
            UploadedFile::fake()->image('poster.jpg'),
        );

        $this->putJson($this->tenantUrl("broadcasts/{$broadcast->id}"), [
            'title' => 'Promo Bergambar',
            'audience' => 'all',
            'template' => $this->template(str_repeat('a', 1100), []),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('template.body');
    }

    /** Tanpa poster, pesan boleh jauh lebih panjang. */
    public function test_a_long_body_is_accepted_without_a_poster(): void
    {
        $this->actingAsClinicUser();

        $broadcast = $this->createBroadcast();

        $this->putJson($this->tenantUrl("broadcasts/{$broadcast->id}"), [
            'title' => 'Promo Panjang',
            'audience' => 'all',
            'template' => $this->template(str_repeat('a', 1500), []),
        ])->assertOk();
    }

    public function test_template_params_must_match_the_placeholders(): void
    {
        $this->actingAsClinicUser();

        $broadcast = $this->createBroadcast();

        $this->putJson($this->tenantUrl("broadcasts/{$broadcast->id}"), [
            'title' => 'Promo',
            'audience' => 'all',
            'template' => $this->template('Halo {{1}} {{2}}', ['nama']),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('template.params');
    }

    /** Kunci audience_params di luar formulir tidak boleh hilang saat disunting. */
    public function test_extra_audience_params_are_preserved(): void
    {
        $this->actingAsClinicUser();

        $broadcast = $this->createBroadcast();
        $broadcast->update(['audience_params' => ['auto' => true, 'days' => 30]]);

        $this->putJson($this->tenantUrl("broadcasts/{$broadcast->id}"), $this->updatePayload())
            ->assertOk();

        $params = $broadcast->fresh()->audience_params;

        $this->assertTrue($params['auto']);
        $this->assertArrayNotHasKey('days', $params);
    }

    public function test_a_broadcast_from_another_clinic_cannot_be_edited(): void
    {
        $other = $this->createTenant('klinik-tetangga');
        $this->actingAsClinicUser();

        // Dibuat di bawah tenancy klinik lain, lalu container dikembalikan ke
        // klinik yang sedang diuji — sama seperti request sungguhan.
        app()->instance('tenant', $other);

        $foreign = Broadcast::create([
            'title' => 'Punya Klinik Lain',
            'kind' => 'promo',
            'audience' => 'all',
            'template' => $this->template(),
            'message' => 'Halo',
        ]);

        app()->instance('tenant', $this->tenant);

        $this->assertSame($other->id, $foreign->tenant_id);

        $this->putJson($this->tenantUrl("broadcasts/{$foreign->id}"), $this->updatePayload())
            ->assertNotFound();
    }

    public function test_can_edit_follows_the_broadcast_status(): void
    {
        $this->actingAsClinicUser();

        $broadcast = $this->createBroadcast();

        $this->getJson($this->tenantUrl("broadcasts/{$broadcast->id}"))
            ->assertOk()
            ->assertJsonPath('data.can_edit', true);

        $broadcast->update(['status' => BroadcastStatus::Sending]);

        $this->getJson($this->tenantUrl("broadcasts/{$broadcast->id}"))
            ->assertOk()
            ->assertJsonPath('data.can_edit', false);
    }
}
