<?php

namespace Tests\Feature\Broadcast;

use App\Actions\Broadcast\RequeueFailedRecipientsAction;
use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\Patient;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Broadcast dikirim lewat gateway WhatsApp internal, bukan template Meta.
 *
 * Yang dijaga di sini: isi pesan yang dirender per penerima benar-benar
 * berangkat apa adanya, kunci idempotensinya berpindah saat kampanye
 * diantrekan ulang, dan sesi yang putus menghentikan kampanye alih-alih
 * menghanguskan seluruh penerima.
 */
class BroadcastGatewaySendTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /** @return array<string, mixed> */
    private function sentEnvelope(string $messageId = 'uuid-blast'): array
    {
        return ['success' => true, 'data' => ['messageId' => $messageId, 'status' => 'QUEUED']];
    }

    private function makeBroadcast(?UploadedFile $image = null): Broadcast
    {
        Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Budi',
            'whatsapp' => '081111111111',
        ]);

        $payload = [
            'title' => 'Promo November',
            'template' => ['body' => 'Halo {{1}}', 'params' => ['nama']],
            'audience' => 'all',
        ];

        if ($image !== null) {
            $payload['image'] = $image;

            $id = $this->post($this->tenantUrl('broadcasts'), $payload, ['Accept' => 'application/json'])
                ->assertCreated()->json('data.id');
        } else {
            $id = $this->postJson($this->tenantUrl('broadcasts'), $payload)
                ->assertCreated()->json('data.id');
        }

        return Broadcast::findOrFail($id);
    }

    /** @param array<string, mixed> $overrides */
    private function runJob(int $recipientId, ?JobContract $queueJob = null): SendBroadcastRecipientJob
    {
        $job = new SendBroadcastRecipientJob($recipientId, $this->tenant->id);

        if ($queueJob !== null) {
            $job->setJob($queueJob);
        }

        $job->handle();

        return $job;
    }

    public function test_job_sends_rendered_text_with_attempt_scoped_key(): void
    {
        $this->actingAsClinicUser();
        $this->configureWhatsappGateway();
        Http::fake(['*' => Http::response($this->sentEnvelope(), 202)]);

        $broadcast = $this->makeBroadcast();
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        $this->runJob($recipient->id);

        $this->assertSame(BroadcastRecipientStatus::Sent, $recipient->fresh()->status);

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/messages/send'
            && $request['text'] === 'Halo Budi'
            && $request->hasHeader('Idempotency-Key', 'broadcast-'.$recipient->id.'-0'));
    }

    public function test_image_broadcast_uses_image_endpoint_with_caption(): void
    {
        $this->actingAsClinicUser();
        $this->configureWhatsappGateway();
        Http::fake(['*' => Http::response($this->sentEnvelope(), 202)]);

        $broadcast = $this->makeBroadcast(UploadedFile::fake()->image('poster.jpg'));
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        $this->runJob($recipient->id);

        $this->assertSame(BroadcastRecipientStatus::Sent, $recipient->fresh()->status);

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/messages/image'
            && $request['to'] === '6281111111111'
            && $request['caption'] === 'Halo Budi'
            && $request['url'] === $broadcast->image_url);
    }

    public function test_requeue_increments_send_attempt_so_idempotency_key_changes(): void
    {
        $this->actingAsClinicUser();
        $this->configureWhatsappGateway();
        // Satu fake untuk dua babak: ditolak, lalu pulih. Http::fake() yang
        // dipanggil dua kali menumpuk stub, bukan menggantinya.
        $rejected = true;
        Http::fake(fn () => $rejected
            ? Http::response([
                'success' => false,
                'message' => 'Nomor tidak terdaftar di WhatsApp.',
                'error' => ['code' => 'INVALID_RECIPIENT'],
            ], 422)
            : Http::response($this->sentEnvelope(), 202));

        $broadcast = $this->makeBroadcast();
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        $this->runJob($recipient->id);
        $this->assertSame(BroadcastRecipientStatus::Failed, $recipient->fresh()->status);

        app(RequeueFailedRecipientsAction::class)->handle($broadcast);

        // Job pertama menutup campaign sebagai gagal; instance di sini masih
        // memegang status lama, jadi disegarkan dulu supaya perubahannya
        // benar-benar tertulis.
        $broadcast->refresh();
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        $this->assertSame(1, $recipient->fresh()->send_attempt);

        $rejected = false;

        $this->runJob($recipient->id);

        Http::assertSent(fn ($request) => $request->hasHeader(
            'Idempotency-Key',
            'broadcast-'.$recipient->id.'-1',
        ));
    }

    public function test_session_not_connected_pauses_campaign(): void
    {
        $this->actingAsClinicUser();
        $this->configureWhatsappGateway();
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'message' => 'Sesi WhatsApp belum tersambung.',
                'error' => ['code' => 'SESSION_NOT_CONNECTED'],
            ], 422),
        ]);

        $broadcast = $this->makeBroadcast();
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        $this->runJob($recipient->id);

        $this->assertSame(BroadcastStatus::Paused, $broadcast->fresh()->status);
        $this->assertSame(BroadcastRecipientStatus::Pending, $recipient->fresh()->status);
    }

    public function test_throttled_send_returns_the_job_to_the_queue(): void
    {
        $this->actingAsClinicUser();
        $this->configureWhatsappGateway();
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'message' => 'Terlalu banyak permintaan.',
                'error' => ['code' => 'RATE_LIMIT_EXCEEDED'],
            ], 429, ['Retry-After' => '90']),
        ]);

        $broadcast = $this->makeBroadcast();
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        $queueJob = Mockery::mock(JobContract::class);
        $queueJob->shouldReceive('release')->once()->with(90);

        $this->runJob($recipient->id, $queueJob);

        $this->assertSame(BroadcastRecipientStatus::Pending, $recipient->fresh()->status);
        $this->assertSame(BroadcastStatus::Sending, $broadcast->fresh()->status);
    }

    public function test_successful_send_is_recorded_to_inbox_on_gateway_channel(): void
    {
        $this->actingAsClinicUser();
        $this->configureWhatsappGateway();
        Http::fake(['*' => Http::response($this->sentEnvelope('uuid-inbox'), 202)]);

        $broadcast = $this->makeBroadcast();
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        $this->runJob($broadcast->recipients()->first()->id);

        $this->assertDatabaseHas('conversations', [
            'tenant_id' => $this->tenant->id,
            'contact_phone' => '6281111111111',
            'channel' => 'gateway',
        ]);

        $this->assertDatabaseHas('messages', [
            'provider_message_id' => 'uuid-inbox',
            'body' => 'Halo Budi',
        ]);
    }

    public function test_template_endpoints_are_gone(): void
    {
        $this->actingAsClinicUser();

        $this->getJson($this->tenantUrl('broadcasts/templates'))->assertNotFound();
        $this->getJson($this->tenantUrl('broadcasts/templates/manage'))->assertNotFound();
    }
}
