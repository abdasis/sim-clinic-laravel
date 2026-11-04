<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\Patient;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Broadcast tidak boleh mengaku berhasil kalau pesannya tidak berangkat.
 *
 * Lahir dari laporan sungguhan: campaign terbaca "Selesai" padahal tidak
 * satu pun pesan sampai. Dua sebab yang dijaga di sini — campaign yang
 * seluruhnya gagal dulu ditutup sebagai selesai, dan pengiriman massal boleh
 * berjalan walau gateway belum siap sehingga tiap pesan hangus satu per satu.
 */
class BroadcastDeliveryTruthTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function gatewayConfigured(): void
    {
        $this->configureWhatsappGateway();
    }

    /** @param array<string, mixed> $fakes */
    private function gateway(array $fakes): void
    {
        $this->gatewayConfigured();
        Http::fake($fakes);
    }

    private function connectedAndSending(): array
    {
        return ['*' => Http::response([
            'success' => true,
            'data' => ['messageId' => 'uuid-ok', 'status' => 'QUEUED'],
        ], 202)];
    }

    private function connectedButRejecting(): array
    {
        return [
            '*' => Http::response([
                'success' => false,
                'message' => 'Recipient not registered',
                'error' => ['code' => 'INVALID_RECIPIENT'],
            ], 422),
        ];
    }

    private function makeBroadcast(int $patients = 2): Broadcast
    {
        foreach (range(1, $patients) as $i) {
            Patient::factory()->create([
                'tenant_id' => $this->tenant->id,
                'whatsapp' => '08'.str_pad((string) $i, 10, '1'),
            ]);
        }

        $id = $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Promo Agustus',
            'template' => [
                'name' => 'promo_bulanan',
                'language' => 'id',
                'body' => 'Halo {{1}}',
                'params' => ['nama'],
            ],
            'audience' => 'all',
        ])->assertCreated()->json('data.id');

        return Broadcast::find($id);
    }

    /** Tiru worker yang percobaannya sudah habis, supaya penerima ditandai gagal. */
    private function runExhausted(int $recipientId): void
    {
        $job = new SendBroadcastRecipientJob($recipientId, $this->tenant->id);

        $queueJob = Mockery::mock(JobContract::class);
        $queueJob->shouldReceive('attempts')->andReturn(3);
        $queueJob->shouldReceive('getJobId')->andReturn('1');
        $job->setJob($queueJob);

        $job->handle();
    }

    /**
     * Inti laporan: seluruh pesan ditolak gateway, tapi campaign-nya dulu
     * ditutup "Selesai" — tidak terbedakan dari blast yang berhasil.
     */
    public function test_a_campaign_where_nothing_was_delivered_is_marked_failed(): void
    {
        $this->actingAsClinicUser();
        $this->gateway($this->connectedButRejecting());

        $broadcast = $this->makeBroadcast();
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        foreach ($broadcast->recipients as $recipient) {
            $this->runExhausted($recipient->id);
        }

        $broadcast->refresh();

        $this->assertSame(BroadcastStatus::Failed, $broadcast->status);
        $this->assertSame('Gagal terkirim', $broadcast->status->label());
        $this->assertSame(0, $broadcast->sentRecipients()->count());
        $this->assertSame(2, $broadcast->failedRecipients()->count());
    }

    /** Satu saja berhasil, campaign-nya memang selesai — bukan gagal. */
    public function test_a_campaign_with_at_least_one_delivery_is_marked_done(): void
    {
        $this->actingAsClinicUser();
        $this->gatewayConfigured();

        $calls = 0;
        Http::fake(function () use (&$calls) {
            // Nomor pertama lolos, sisanya ditolak.
            return ++$calls === 1
                ? Http::response([
                    'success' => true,
                    'data' => ['messageId' => 'uuid-ok', 'status' => 'QUEUED'],
                ], 202)
                : Http::response([
                    'success' => false,
                    'message' => 'Recipient not registered',
                    'error' => ['code' => 'INVALID_RECIPIENT'],
                ], 422);
        });

        $broadcast = $this->makeBroadcast();
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        foreach ($broadcast->recipients as $recipient) {
            $this->runExhausted($recipient->id);
        }

        $broadcast->refresh();

        $this->assertSame(BroadcastStatus::Done, $broadcast->status);
        $this->assertSame(1, $broadcast->sentRecipients()->count());
        $this->assertSame(1, $broadcast->failedRecipients()->count());
    }

    /**
     * Penjagaan yang mencegah seluruh kejadian di atas: gateway yang belum
     * siap tidak boleh menghanguskan ratusan pesan satu per satu.
     */
    public function test_sending_is_refused_while_the_gateway_is_not_configured(): void
    {
        Queue::fake();
        $this->actingAsClinicUser();

        $broadcast = $this->makeBroadcast();

        $this->postJson($this->tenantUrl("broadcasts/{$broadcast->id}/send"))
            ->assertStatus(422)
            ->assertJsonPath('message', __('broadcast.gateway_not_ready'));

        Queue::assertNothingPushed();
        $this->assertSame(BroadcastStatus::Ready, $broadcast->fresh()->status);
        $this->assertSame(2, $broadcast->pendingRecipients()->count());
    }

    /** Broadcast tanpa template juga ditolak di depan, bukan di worker. */
    public function test_sending_is_refused_when_no_template_is_chosen(): void
    {
        Queue::fake();
        $this->actingAsClinicUser();
        $this->gatewayConfigured();

        $broadcast = $this->makeBroadcast();
        $broadcast->update(['template' => null]);

        $this->postJson($this->tenantUrl("broadcasts/{$broadcast->id}/send"))
            ->assertStatus(422)
            ->assertJsonPath('message', __('broadcast.template_required'));

        Queue::assertNothingPushed();
    }

    /**
     * Setelah gateway pulih, menekan kirim lagi harus mengulang yang gagal.
     * Tanpa ini campaign yang seluruhnya gagal jadi jalan buntu.
     */
    public function test_pressing_send_again_retries_the_failed_recipients(): void
    {
        $this->actingAsClinicUser();
        $this->gateway($this->connectedButRejecting());

        $broadcast = $this->makeBroadcast();
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        foreach ($broadcast->recipients as $recipient) {
            $this->runExhausted($recipient->id);
        }

        $this->assertSame(BroadcastStatus::Failed, $broadcast->fresh()->status);

        // Gateway pulih, admin menekan kirim lagi.
        Queue::fake();
        Http::fake($this->connectedAndSending());

        $this->postJson($this->tenantUrl("broadcasts/{$broadcast->id}/send"))
            ->assertOk()
            ->assertJsonPath('data.queued', 2);

        Queue::assertPushed(SendBroadcastRecipientJob::class, 2);
        $this->assertSame(2, $broadcast->pendingRecipients()->count());
        $this->assertSame(0, $broadcast->failedRecipients()->count());
    }

    /** Angka gagalnya harus sampai ke layar; selama ini tidak pernah dikirim. */
    public function test_the_failed_count_reaches_the_client(): void
    {
        $this->actingAsClinicUser();
        $this->gateway($this->connectedButRejecting());

        $broadcast = $this->makeBroadcast();
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $this->runExhausted($broadcast->recipients()->first()->id);

        $this->getJson($this->tenantUrl("broadcasts/{$broadcast->id}"))
            ->assertOk()
            ->assertJsonPath('data.recipients_total', 2)
            ->assertJsonPath('data.recipients_failed', 1)
            ->assertJsonPath('data.recipients_sent', 0);
    }

    /** Status gagal ditetapkan sistem, bukan dipilih admin. */
    public function test_failed_is_not_a_status_an_admin_can_set(): void
    {
        $this->actingAsClinicUser();
        $this->gatewayConfigured();

        $broadcast = $this->makeBroadcast();

        $this->patchJson($this->tenantUrl("broadcasts/{$broadcast->id}/status"), ['status' => 'failed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame(BroadcastStatus::Ready, $broadcast->fresh()->status);
    }

    /** Penerima yang sudah terkirim tidak boleh ikut terkirim dua kali. */
    public function test_resending_does_not_touch_already_delivered_recipients(): void
    {
        Queue::fake();
        $this->actingAsClinicUser();
        $this->gateway($this->connectedAndSending());

        $broadcast = $this->makeBroadcast();
        $first = $broadcast->recipients()->first();
        $first->update(['status' => BroadcastRecipientStatus::Sent, 'sent_at' => now()]);

        $this->postJson($this->tenantUrl("broadcasts/{$broadcast->id}/send"))
            ->assertOk()
            ->assertJsonPath('data.queued', 1);

        Queue::assertPushed(SendBroadcastRecipientJob::class, 1);
        $this->assertSame(BroadcastRecipientStatus::Sent, $first->fresh()->status);
    }
}
