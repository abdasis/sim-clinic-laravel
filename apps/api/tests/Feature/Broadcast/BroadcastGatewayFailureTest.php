<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\Patient;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use App\Support\WahaException;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Apa yang terjadi saat gateway menolak di tengah blast.
 *
 * Lahir dari laporan sungguhan (isu #320): dari 130 penerima hanya 4 yang
 * berangkat, 124 ditolak "HTTP 422", 2 tersangkut menunggu selamanya, dan
 * campaign-nya berhenti di "sedang mengirim". Yang diuji di sini bukan cuma
 * hitungannya, tapi apakah aplikasinya menyisakan cukup keterangan untuk
 * tahu sebabnya — laporan itu sendiri hanya bisa menduga.
 */
class BroadcastGatewayFailureTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function configured(): void
    {
        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);
        WahaSetting::create(['base_url' => 'https://waha.test', 'api_key' => 'kunci']);
    }

    private function makeBroadcast(int $patients = 3): Broadcast
    {
        foreach (range(1, $patients) as $i) {
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

        return Broadcast::find($id);
    }

    /** Tiru worker pada percobaan ke-$attempt. */
    private function runAttempt(int $recipientId, int $attempt): void
    {
        $job = new SendBroadcastRecipientJob($recipientId, $this->tenant->id);

        $queueJob = Mockery::mock(JobContract::class);
        $queueJob->shouldReceive('attempts')->andReturn($attempt);
        $queueJob->shouldReceive('getJobId')->andReturn('1');
        $job->setJob($queueJob);

        $job->handle();
    }

    /** Tiru worker yang percobaannya sudah habis. */
    private function runExhausted(int $recipientId): void
    {
        $this->runAttempt($recipientId, 99);
    }

    /**
     * Gangguan sesaat yang sungguhan terjadi di lapangan: halaman WhatsApp
     * Web milik gateway dimuat ulang di tengah blast. WAHA membalas 500
     * dengan ProtocolError, dan sesinya sebentar berstatus STARTING.
     *
     * @return array<string, mixed>
     */
    private function browserCrash(string $session = 'STARTING'): array
    {
        return [
            'waha.test/api/sessions/*' => Http::response(['status' => $session], 200),
            'waha.test/api/sendText' => Http::response([
                'statusCode' => 500,
                'exception' => [
                    'name' => 'ProtocolError',
                    'message' => 'Protocol error (Runtime.callFunctionOn): Execution context was destroyed.',
                ],
            ], 500),
        ];
    }

    /**
     * Jeda antar pesan sudah ada dan bukan sebab kegagalannya.
     *
     * Laporan menduga pengiriman terlalu cepat, padahal tiap job sudah
     * dijadwalkan 5 detik berjarak. Dikunci di sini supaya "perlambat lagi"
     * tidak dipakai sebagai jawaban atas gejala yang sebabnya lain.
     */
    public function test_messages_are_already_paced_five_seconds_apart(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        Http::fake(['waha.test/api/sessions/*' => Http::response(['status' => 'WORKING'], 200)]);
        Queue::fake();

        $broadcast = $this->makeBroadcast(3);

        $this->postJson($this->tenantUrl("broadcasts/{$broadcast->id}/send"))->assertOk();

        $delays = [];
        Queue::assertPushed(SendBroadcastRecipientJob::class, function ($job) use (&$delays) {
            $delays[] = (int) round(now()->diffInSeconds($job->delay, absolute: true));

            return true;
        });

        sort($delays);
        $this->assertSame([0, 5, 10], $delays, 'pesan tidak dijadwalkan berjarak 5 detik');
    }

    /**
     * Alasan penolakan gateway harus ikut tercatat.
     *
     * Tanpa isi balasannya yang tersisa cuma "HTTP 422" — angka yang sama
     * untuk sesi terputus, nomor ditolak, dan payload cacat. Persis itu yang
     * membuat laporan #320 hanya bisa menebak sebabnya.
     */
    public function test_the_gateway_rejection_reason_is_recorded(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        Http::fake([
            'waha.test/api/sessions/*' => Http::response(['status' => 'WORKING'], 200),
            'waha.test/api/sendText' => Http::response(
                ['message' => "Session status is not as expected. Expected 'WORKING', got 'STOPPED'"],
                422,
            ),
        ]);

        $broadcast = $this->makeBroadcast(1);
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        $this->runExhausted($recipient->id);

        $this->assertStringContainsString(
            'STOPPED',
            (string) $recipient->fresh()->error,
            'alasan penolakan gateway tidak tersimpan; yang tersisa cuma kode status',
        );
    }

    /**
     * Sesi yang putus di tengah blast menghentikan campaign, bukan
     * menghanguskan sisanya satu per satu.
     *
     * 124 penerima ditandai gagal padahal tidak ada yang salah dengan
     * nomornya — yang putus sesinya. Setelah dijeda, sisanya masih menunggu
     * dan bisa dilanjutkan begitu WhatsApp tersambung lagi.
     */
    public function test_a_dropped_session_pauses_the_campaign_instead_of_burning_recipients(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        Http::fake([
            'waha.test/api/sessions/*' => Http::response(['status' => 'STOPPED'], 200),
            'waha.test/api/sendText' => Http::response(['message' => 'Session status is not as expected'], 422),
        ]);

        $broadcast = $this->makeBroadcast(3);
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        $this->runExhausted($broadcast->recipients()->orderBy('id')->first()->id);

        $broadcast->refresh();

        $this->assertSame(
            BroadcastStatus::Paused,
            $broadcast->status,
            'campaign tidak dijeda saat sesi putus',
        );
        // Termasuk nomor yang barusan ditolak: pesannya tidak berangkat ke
        // mana pun, jadi ia tetap menunggu dan ikut terkirim saat dilanjutkan.
        $this->assertSame(
            3,
            $broadcast->recipients()->where('status', BroadcastRecipientStatus::Pending)->count(),
            'penerima ikut hangus padahal sesinya yang putus',
        );
    }

    /**
     * Campaign yang dijeda gateway menyebutkan sebabnya.
     *
     * Dijeda tanpa keterangan terbaca persis sama dengan dijeda admin, jadi
     * yang tersisa di layar cuma campaign berhenti tanpa petunjuk apakah ada
     * yang perlu dibereskan dulu sebelum dilanjutkan.
     */
    public function test_the_pause_states_what_the_gateway_said(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        Http::fake([
            'waha.test/api/sessions/*' => Http::response(['status' => 'STOPPED'], 200),
            'waha.test/api/sendText' => Http::response(
                ['message' => "Session status is not as expected. Expected 'WORKING', got 'STOPPED'"],
                422,
            ),
        ]);

        $broadcast = $this->makeBroadcast(2);
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        $this->runExhausted($broadcast->recipients()->orderBy('id')->first()->id);

        $this->assertStringContainsString('STOPPED', (string) $broadcast->fresh()->paused_reason);
    }

    /**
     * Blast yang berhenti di tengah bisa dilanjutkan setelah WhatsApp-nya
     * tersambung lagi — tanpa menyusun campaign baru.
     *
     * Yang sudah terkirim tidak dikirim ulang; yang belum tetap antre.
     */
    public function test_a_paused_campaign_resumes_where_it_stopped(): void
    {
        $this->actingAsClinicUser();
        $this->configured();

        // Satu fake untuk dua babak: sesinya putus, lalu tersambung lagi.
        // Http::fake() yang dipanggil dua kali menumpuk stub, bukan
        // menggantinya — babak pertama akan menang terus.
        $session = 'STOPPED';
        Http::fake(function ($request) use (&$session) {
            if (str_contains($request->url(), '/api/sessions')) {
                return Http::response(['status' => $session], 200);
            }

            return $session === 'WORKING'
                ? Http::response(['id' => 'true_628@c.us'], 201)
                : Http::response(['message' => 'Session status is not as expected'], 422);
        });

        $broadcast = $this->makeBroadcast(3);
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        // Satu berhasil sebelum sesinya putus, seperti 4 dari 130 di laporan.
        $sent = $broadcast->recipients()->orderBy('id')->first();
        $sent->update(['status' => BroadcastRecipientStatus::Sent, 'sent_at' => now()]);

        $this->runExhausted($broadcast->recipients()->orderBy('id')->skip(1)->first()->id);
        $this->assertSame(BroadcastStatus::Paused, $broadcast->fresh()->status);

        // WhatsApp tersambung lagi, admin menekan "Lanjutkan".
        $session = 'WORKING';
        Queue::fake();

        $this->patchJson($this->tenantUrl("broadcasts/{$broadcast->id}/status"), ['status' => 'sending'])
            ->assertOk();

        $broadcast->refresh();

        $this->assertSame(BroadcastStatus::Sending, $broadcast->status);
        $this->assertNull($broadcast->paused_reason, 'keterangan jeda lama masih menempel');
        $this->assertSame('sent', $sent->fresh()->status->value, 'yang sudah terkirim ikut dikirim ulang');
        Queue::assertPushed(SendBroadcastRecipientJob::class, 2);
    }

    /**
     * Gateway yang tersendat sesaat ditunggu, bukan dijadikan alasan
     * menghentikan campaign.
     *
     * Halaman WhatsApp Web milik gateway dimuat ulang di tengah blast lalu
     * pulih sendiri semenit kemudian. Menjeda seluruh campaign untuk itu
     * berarti tiap kedipan gateway menuntut orang menunggui layar.
     */
    public function test_a_passing_gateway_hiccup_is_retried_not_paused(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        Http::fake($this->browserCrash());

        $broadcast = $this->makeBroadcast(3);
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->orderBy('id')->first();

        // Percobaan pertama: job melempar supaya antrean mengulangnya nanti.
        try {
            $this->runAttempt($recipient->id, 1);
            $this->fail('job tidak melempar, jadi antrean tidak akan mengulangnya');
        } catch (WahaException $e) {
            $this->assertSame(500, $e->status);
        }

        $this->assertSame(BroadcastStatus::Sending, $broadcast->fresh()->status, 'campaign dijeda padahal cuma tersendat');
        $this->assertSame(BroadcastRecipientStatus::Pending, $recipient->fresh()->status);
    }

    /**
     * Tersendat yang tidak pulih-pulih tetap tidak menghanguskan nomornya.
     *
     * Setelah percobaannya habis, yang salah tetap gatewaynya — jadi
     * campaign-nya yang berhenti, bukan orang yang kebetulan sedang antre.
     */
    public function test_a_gateway_that_never_recovers_pauses_instead_of_burning(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        Http::fake($this->browserCrash());

        $broadcast = $this->makeBroadcast(3);
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->orderBy('id')->first();

        $this->runExhausted($recipient->id);

        $this->assertSame(BroadcastStatus::Paused, $broadcast->fresh()->status);
        $this->assertSame(BroadcastRecipientStatus::Pending, $recipient->fresh()->status);
    }

    /**
     * Nomor yang ditolak gateway sehat tidak menghentikan yang lain.
     *
     * Satu nomor mati di daftar 130 pasien bukan alasan menahan 129 sisanya,
     * dan penolakan begini menjawab sama persis tiap kali — tidak ada gunanya
     * diulang.
     */
    public function test_a_rejected_number_fails_alone_and_is_not_retried(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        Http::fake([
            'waha.test/api/sessions/*' => Http::response(['status' => 'WORKING'], 200),
            'waha.test/api/sendText' => Http::response(['message' => 'Number not registered on WhatsApp'], 422),
        ]);

        $broadcast = $this->makeBroadcast(3);
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->orderBy('id')->first();

        // Percobaan pertama, jauh dari batas: tetap ditandai gagal saat itu
        // juga, bukan diulang.
        $this->runAttempt($recipient->id, 1);

        $this->assertSame(BroadcastRecipientStatus::Failed, $recipient->fresh()->status);
        $this->assertStringContainsString('Number not registered', (string) $recipient->fresh()->error);
        $this->assertSame(BroadcastStatus::Sending, $broadcast->fresh()->status, 'satu nomor mati menghentikan seluruh blast');
    }

    /**
     * Job yang mati di luar handle() tidak boleh meninggalkan penerima
     * menunggu selamanya.
     *
     * Dua penerima di laporan tersangkut "menunggu" dan campaign-nya tidak
     * pernah menutup — worker berhenti, dan tidak ada yang menandai sisanya.
     */
    public function test_a_job_that_dies_outside_handle_still_marks_the_recipient(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        Http::fake(['waha.test/api/sessions/*' => Http::response(['status' => 'WORKING'], 200)]);

        $broadcast = $this->makeBroadcast(1);
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        (new SendBroadcastRecipientJob($recipient->id, $this->tenant->id))
            ->failed(new \RuntimeException('worker mati'));

        $this->assertSame(
            BroadcastRecipientStatus::Failed,
            $recipient->fresh()->status,
            'penerima tersangkut menunggu setelah job-nya mati',
        );
        $this->assertNotSame(
            BroadcastStatus::Sending,
            $broadcast->fresh()->status,
            'campaign tersangkut "sedang mengirim" tanpa ada yang menutup',
        );
    }
}
