<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\Patient;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Blast yang berhenti karena sesi gateway tersendat dilanjutkan sendiri.
 *
 * Sesi WhatsApp bisa putus sesaat di tengah kiriman ratusan pesan lalu pulih
 * semenit kemudian. Tanpa penjadwal ini, tiap kedipan berarti seseorang harus
 * menunggui layar dan menekan Lanjutkan — blast 130 pasien jadi pekerjaan
 * menjaga, bukan pekerjaan mengirim.
 */
class ResumeStalledBroadcastsTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function configured(): void
    {
        $this->configureWhatsappGateway();
    }

    /** Campaign yang tertinggal separuh jalan, dijeda gateway. */
    private function stalled(int $waiting = 2, array $overrides = []): Broadcast
    {
        foreach (range(1, $waiting) as $i) {
            Patient::factory()->create([
                'tenant_id' => $this->tenant->id,
                'whatsapp' => '08'.str_pad((string) $i, 10, '1'),
            ]);
        }

        $id = $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'PROMO SEPTEMBER',
            'template' => [
                'name' => 'promo_bulanan',
                'language' => 'id',
                'body' => 'Halo {{1}}',
                'params' => ['nama'],
            ],
            'audience' => 'all',
        ])->assertCreated()->json('data.id');

        $broadcast = Broadcast::find($id);
        $broadcast->update([
            'status' => BroadcastStatus::Paused,
            'paused_reason' => 'Cloud API Meta menolak pengiriman: HTTP 503 — Upstream unavailable',
            ...$overrides,
        ]);

        return $broadcast;
    }

    public function test_a_stalled_campaign_resumes_once_the_gateway_is_configured(): void
    {
        $this->actingAsClinicUser();
        $this->configured();

        $broadcast = $this->stalled(2);

        Queue::fake();

        $this->artisan('clinic:resume-broadcasts')->assertSuccessful();

        $broadcast->refresh();

        $this->assertSame(BroadcastStatus::Sending, $broadcast->status);
        $this->assertNull($broadcast->paused_reason);
        $this->assertSame(1, $broadcast->auto_resumes);
        Queue::assertPushed(SendBroadcastRecipientJob::class, 2);
    }

    /** Gateway yang belum disetel tidak disentuh — kirim sekarang cuma gagal lagi. */
    public function test_it_waits_while_the_gateway_is_not_configured(): void
    {
        $this->actingAsClinicUser();

        $broadcast = $this->stalled(2);

        Queue::fake();

        $this->artisan('clinic:resume-broadcasts')->assertSuccessful();

        $this->assertSame(BroadcastStatus::Paused, $broadcast->fresh()->status);
        Queue::assertNothingPushed();
    }

    /**
     * Yang dijeda orang tetap dijeda.
     *
     * Admin yang menekan Jeda punya alasannya sendiri — mengoreksi teksnya,
     * menunda promonya — dan penjadwal tidak berhak membatalkan keputusan itu.
     * Bedanya terbaca dari keterangan jeda yang kosong.
     */
    public function test_a_campaign_paused_by_a_person_is_left_alone(): void
    {
        $this->actingAsClinicUser();
        $this->configured();

        $broadcast = $this->stalled(2, ['paused_reason' => null]);

        Queue::fake();

        $this->artisan('clinic:resume-broadcasts')->assertSuccessful();

        $this->assertSame(BroadcastStatus::Paused, $broadcast->fresh()->status);
        Queue::assertNothingPushed();
    }

    /**
     * Gateway yang putus-nyambung terus tidak digedor selamanya.
     *
     * Setelah sekian percobaan, campaign-nya dibiarkan menunggu orang —
     * mengulang tanpa batas tidak membuat gateway membaik, cuma menyembunyikan
     * bahwa ada yang perlu dibereskan.
     */
    public function test_it_gives_up_after_too_many_automatic_attempts(): void
    {
        $this->actingAsClinicUser();
        $this->configured();

        $broadcast = $this->stalled(2, ['auto_resumes' => 5]);

        Queue::fake();

        $this->artisan('clinic:resume-broadcasts')->assertSuccessful();

        $this->assertSame(BroadcastStatus::Paused, $broadcast->fresh()->status);
        Queue::assertNothingPushed();
    }

    /**
     * Campaign yang berhenti karena laju ditahan menunggu masa diamnya habis.
     *
     * Melanjutkan lima menit kemudian, seperti gangguan biasa, sama saja
     * dengan mengabaikan peringatan yang baru saja diberikan Meta — dan itu
     * yang berujung nomor klinik diblokir.
     */
    public function test_a_cooling_off_campaign_is_not_resumed_early(): void
    {
        $this->actingAsClinicUser();
        $this->configured();

        $broadcast = $this->stalled(2, [
            'paused_reason' => 'Cloud API Meta menolak pengiriman: HTTP 429 — Too many requests',
            'resume_after' => now()->addMinutes(25),
        ]);

        Queue::fake();

        $this->artisan('clinic:resume-broadcasts')->assertSuccessful();

        $this->assertSame(BroadcastStatus::Paused, $broadcast->fresh()->status);
        Queue::assertNothingPushed();
    }

    /** Begitu masa diamnya lewat, campaign-nya jalan lagi sendiri. */
    public function test_it_resumes_once_the_cooling_off_has_passed(): void
    {
        $this->actingAsClinicUser();
        $this->configured();

        $broadcast = $this->stalled(2, [
            'paused_reason' => 'Cloud API Meta menolak pengiriman: HTTP 429 — Too many requests',
            'resume_after' => now()->subMinute(),
        ]);

        Queue::fake();

        $this->artisan('clinic:resume-broadcasts')->assertSuccessful();

        $broadcast->refresh();

        $this->assertSame(BroadcastStatus::Sending, $broadcast->status);
        $this->assertNull($broadcast->resume_after);
        Queue::assertPushed(SendBroadcastRecipientJob::class, 2);
    }

    /**
     * Perintahnya benar-benar terjadwal.
     *
     * Lanjut-otomatis yang tidak pernah dipanggil scheduler sama saja dengan
     * tidak ada, dan tidak ada yang menyadarinya sampai blast berikutnya
     * berhenti menunggu orang.
     */
    public function test_the_command_is_actually_scheduled(): void
    {
        $events = app(Schedule::class)->events();

        $this->assertNotEmpty(
            array_filter($events, fn ($event) => str_contains($event->command ?? '', 'clinic:resume-broadcasts')),
            'clinic:resume-broadcasts tidak terdaftar di scheduler',
        );
    }

    /** Orang yang menekan Lanjutkan mengembalikan jatah percobaannya. */
    public function test_a_person_resuming_resets_the_automatic_budget(): void
    {
        $this->actingAsClinicUser();
        $this->configured();

        $broadcast = $this->stalled(2, ['auto_resumes' => 4]);

        Queue::fake();

        $this->patchJson($this->tenantUrl("broadcasts/{$broadcast->id}/status"), ['status' => 'sending'])
            ->assertOk();

        $this->assertSame(0, $broadcast->fresh()->auto_resumes);
    }
}
