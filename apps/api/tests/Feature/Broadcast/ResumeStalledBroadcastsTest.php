<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\Patient;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Blast yang berhenti karena gatewaynya tersendat dilanjutkan sendiri.
 *
 * Halaman WhatsApp Web milik gateway kerap dimuat ulang di tengah kiriman
 * ratusan pesan lalu pulih semenit kemudian. Tanpa penjadwal ini, tiap
 * kedipan berarti seseorang harus menunggui layar dan menekan Lanjutkan —
 * blast 130 pasien jadi pekerjaan menjaga, bukan pekerjaan mengirim.
 */
class ResumeStalledBroadcastsTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function configured(): void
    {
        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);
        WahaSetting::create(['base_url' => 'https://waha.test', 'api_key' => 'kunci']);
    }

    private function sessionIs(string $status): void
    {
        Http::fake([
            'waha.test/api/sessions/*' => Http::response(['status' => $status], 200),
            'waha.test/*' => Http::response(['id' => 'true_628@c.us'], 201),
        ]);
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
            'message' => 'Halo {nama}',
            'audience' => 'all',
        ])->assertCreated()->json('data.id');

        $broadcast = Broadcast::find($id);
        $broadcast->update([
            'status' => BroadcastStatus::Paused,
            'paused_reason' => 'WAHA menolak pengiriman gambar: HTTP 500 — ProtocolError',
            ...$overrides,
        ]);

        return $broadcast;
    }

    public function test_a_gateway_paused_campaign_resumes_once_the_session_is_back(): void
    {
        $this->actingAsClinicUser();
        $this->configured();

        $broadcast = $this->stalled(2);

        $this->sessionIs('WORKING');
        Queue::fake();

        $this->artisan('clinic:resume-broadcasts')->assertSuccessful();

        $broadcast->refresh();

        $this->assertSame(BroadcastStatus::Sending, $broadcast->status);
        $this->assertNull($broadcast->paused_reason);
        $this->assertSame(1, $broadcast->auto_resumes);
        Queue::assertPushed(SendBroadcastRecipientJob::class, 2);
    }

    /** Sesi yang belum pulih tidak disentuh — kirim sekarang cuma gagal lagi. */
    public function test_it_waits_while_the_session_is_still_down(): void
    {
        $this->actingAsClinicUser();
        $this->configured();

        $broadcast = $this->stalled(2);

        $this->sessionIs('SCAN_QR_CODE');
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

        $this->sessionIs('WORKING');
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

        $this->sessionIs('WORKING');
        Queue::fake();

        $this->artisan('clinic:resume-broadcasts')->assertSuccessful();

        $this->assertSame(BroadcastStatus::Paused, $broadcast->fresh()->status);
        Queue::assertNothingPushed();
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

        $this->sessionIs('WORKING');
        Queue::fake();

        $this->patchJson($this->tenantUrl("broadcasts/{$broadcast->id}/status"), ['status' => 'sending'])
            ->assertOk();

        $this->assertSame(0, $broadcast->fresh()->auto_resumes);
    }
}
