<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Gateway mengambil poster dari internet, jadi alamat lokal/internal ditolak.
 *
 * Penolakan itu semula baru muncul saat worker mengirim, setelah seluruh
 * penerima diantrekan dan gagal satu per satu. Pemeriksaan di depan meniru
 * aturan gateway supaya admin melihat sebabnya sebelum menekan kirim.
 */
class BroadcastPosterReachabilityTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    /** @return array<string, mixed> */
    private function createWithPosterAt(string $diskUrl): Broadcast
    {
        Storage::fake('public', ['url' => $diskUrl]);

        Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'whatsapp' => '081111111111',
        ]);

        $id = $this->post($this->tenantUrl('broadcasts'), [
            'title' => 'Promo November',
            'template' => ['body' => 'Halo {{1}}', 'params' => ['nama']],
            'audience' => 'all',
            'image' => UploadedFile::fake()->image('poster.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        return Broadcast::findOrFail($id);
    }

    private function refusePoster(Broadcast $broadcast): void
    {
        Queue::fake();

        $this->postJson($this->tenantUrl("broadcasts/{$broadcast->id}/send"))
            ->assertStatus(422)
            ->assertJsonPath('message', __('broadcast.image_url_unreachable'));

        Queue::assertNothingPushed();
        $this->assertSame(BroadcastStatus::Ready, $broadcast->fresh()->status);
    }

    public function test_loopback_poster_url_is_refused_before_queueing(): void
    {
        $this->actingAsClinicUser();
        $this->configureWhatsappGateway();

        $this->refusePoster($this->createWithPosterAt('http://127.0.0.1'));
    }

    public function test_localhost_poster_url_is_refused_before_queueing(): void
    {
        $this->actingAsClinicUser();
        $this->configureWhatsappGateway();

        $this->refusePoster($this->createWithPosterAt('http://localhost'));
    }

    public function test_public_poster_url_is_queued(): void
    {
        $this->actingAsClinicUser();
        $this->configureWhatsappGateway();
        Queue::fake();

        $broadcast = $this->createWithPosterAt('https://cdn.contoh.com');

        $this->postJson($this->tenantUrl("broadcasts/{$broadcast->id}/send"))->assertOk();

        Queue::assertPushed(SendBroadcastRecipientJob::class);
    }
}
