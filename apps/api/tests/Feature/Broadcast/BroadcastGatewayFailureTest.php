<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\Patient;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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
        $this->configureWhatsappGateway();
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
            'template' => [
                'body' => 'Halo {{1}}',
                'params' => ['nama'],
            ],
            'audience' => 'all',
        ])->assertCreated()->json('data.id');

        return Broadcast::find($id);
    }

    private function runAttempt(int $recipientId, ?JobContract $queueJob = null): void
    {
        $job = new SendBroadcastRecipientJob($recipientId, $this->tenant->id);

        if ($queueJob !== null) {
            $job->setJob($queueJob);
        }

        $job->handle();
    }

    /** @param array<string, string> $error */
    private function rejection(array $error, int $status): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'message' => $error['message'],
                'error' => ['code' => $error['code']],
            ], $status),
        ]);
    }

    /**
     * Jeda antar pesan sudah ada, dan tidak berirama.
     *
     * Laporan #320 menduga pengiriman terlalu cepat, padahal tiap job sudah
     * dijadwalkan berjarak. Yang dikunci di sini dua hal: jaraknya memang ada
     * (jadi "perlambat lagi" bukan jawaban atas gejala yang sebabnya lain),
     * dan jaraknya tidak seragam — ratusan pesan dengan selang persis sama
     * adalah irama yang tidak pernah dihasilkan orang mengetik, dan justru
     * itu yang gampang dibaca sebagai bot.
     */
    public function test_messages_are_paced_with_an_uneven_gap(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        Queue::fake();

        $broadcast = $this->makeBroadcast(30);

        $this->postJson($this->tenantUrl("broadcasts/{$broadcast->id}/send"))->assertOk();

        $delays = [];
        Queue::assertPushed(SendBroadcastRecipientJob::class, function ($job) use (&$delays) {
            $delays[] = (int) round(now()->diffInSeconds($job->delay, absolute: true));

            return true;
        });

        sort($delays);

        $gaps = [];
        for ($i = 1; $i < count($delays); $i++) {
            $gaps[] = $delays[$i] - $delays[$i - 1];
        }

        $this->assertSame(0, $delays[0], 'pesan pertama tidak berangkat langsung');
        $this->assertGreaterThan(1, count(array_unique($gaps)), 'jeda antar pesan masih seragam');

        foreach ($gaps as $gap) {
            $this->assertGreaterThanOrEqual(3, $gap, 'ada pesan yang menyembur terlalu rapat');
            $this->assertLessThanOrEqual(7, $gap, 'ada jeda yang jauh lebih lama dari seharusnya');
        }

        // Rata-ratanya tetap sekitar 5 detik, jadi durasi blast tidak berubah.
        $this->assertEqualsWithDelta(5.0, array_sum($gaps) / count($gaps), 1.0);
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
        $this->rejection(['message' => 'Nomor tidak valid.', 'code' => 'INVALID_PHONE_NUMBER'], 422);

        $broadcast = $this->makeBroadcast(1);
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        $this->runAttempt($recipient->id);

        $this->assertStringContainsString(
            'Nomor tidak valid',
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
        $this->rejection(['message' => 'Sesi WhatsApp belum tersambung.', 'code' => 'SESSION_NOT_CONNECTED'], 422);

        $broadcast = $this->makeBroadcast(3);
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        $this->runAttempt($broadcast->recipients()->orderBy('id')->first()->id);

        $broadcast->refresh();

        $this->assertSame(
            BroadcastStatus::Paused,
            $broadcast->status,
            'campaign tidak dijeda saat sesi WhatsApp putus',
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
        $this->rejection(['message' => 'Sesi WhatsApp terputus. Pindai ulang QR.', 'code' => 'SESSION_NOT_FOUND'], 422);

        $broadcast = $this->makeBroadcast(2);
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        $this->runAttempt($broadcast->recipients()->orderBy('id')->first()->id);

        $this->assertStringContainsString(
            'Pindai ulang QR',
            (string) $broadcast->fresh()->paused_reason,
        );
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

        // Satu fake untuk dua babak: sesi putus, lalu pulih. Http::fake() yang
        // dipanggil dua kali menumpuk stub, bukan menggantinya.
        $healthy = false;
        Http::fake(fn () => $healthy
            ? Http::response([
                'success' => true,
                'data' => ['messageId' => 'uuid-ok', 'status' => 'QUEUED'],
            ], 202)
            : Http::response([
                'success' => false,
                'message' => 'Sesi WhatsApp belum tersambung.',
                'error' => ['code' => 'SESSION_NOT_CONNECTED'],
            ], 422));

        $broadcast = $this->makeBroadcast(3);
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        // Satu berhasil sebelum sesinya putus, seperti 4 dari 130 di laporan.
        $sent = $broadcast->recipients()->orderBy('id')->first();
        $sent->update(['status' => BroadcastRecipientStatus::Sent, 'sent_at' => now()]);

        $this->runAttempt($broadcast->recipients()->orderBy('id')->skip(1)->first()->id);
        $this->assertSame(BroadcastStatus::Paused, $broadcast->fresh()->status);

        // WhatsApp tersambung lagi, admin menekan "Lanjutkan".
        $healthy = true;
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
     * 5xx bukan penolakan nomor: gateway belum sempat menilai penerimanya.
     * Menandainya gagal menghabiskan seluruh antrean karena satu gangguan
     * yang sama, jadi campaign dijeda dan sisanya tetap menunggu.
     */
    public function test_a_server_error_pauses_the_campaign_instead_of_burning_recipients(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        $http500 = ['message' => 'Upstream service temporarily unavailable.', 'code' => 'HTTP_500'];
        $this->rejection($http500, 500);

        $broadcast = $this->makeBroadcast(3);
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        $this->runAttempt($broadcast->recipients()->orderBy('id')->first()->id);

        $broadcast->refresh();

        $this->assertSame(BroadcastStatus::Paused, $broadcast->status);
        $this->assertStringContainsString(
            'Upstream service temporarily unavailable',
            (string) $broadcast->paused_reason,
            'alasan jeda tidak terbaca admin',
        );
        $this->assertSame(
            3,
            $broadcast->recipients()->where('status', BroadcastRecipientStatus::Pending)->count(),
            'penerima hangus padahal gateway yang bermasalah',
        );
    }

    /**
     * Jaringan yang tidak pernah sampai ke gateway juga bukan salah nomor.
     * Dulu timeout menandai penerima gagal satu per satu sampai antreannya
     * habis tanpa satu pun pesan berangkat.
     */
    public function test_a_network_timeout_pauses_the_campaign_and_keeps_recipients_pending(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        Http::fake(fn () => throw new ConnectionException('Koneksi habis waktu.'));

        $broadcast = $this->makeBroadcast(2);
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        $this->runAttempt($broadcast->recipients()->orderBy('id')->first()->id);

        $this->assertSame(BroadcastStatus::Paused, $broadcast->fresh()->status);
        $this->assertStringContainsString('Koneksi habis waktu.', (string) $broadcast->fresh()->paused_reason);
        $this->assertSame(
            2,
            $broadcast->recipients()->where('status', BroadcastRecipientStatus::Pending)->count(),
        );
    }

    /**
     * Kredensial yang ditolak (401/403) berlaku untuk seluruh antrean.
     * Menghanguskan penerima satu per satu menyembunyikan bahwa yang perlu
     * diperbaiki adalah sambungannya.
     */
    public function test_unauthorized_and_forbidden_pause_the_campaign(): void
    {
        $this->actingAsClinicUser();
        $this->configured();

        $status = 401;
        $message = 'API key tidak sah.';
        $code = 'UNAUTHORIZED';

        Http::fake(function () use (&$status, &$message, &$code) {
            return Http::response([
                'success' => false,
                'message' => $message,
                'error' => ['code' => $code],
            ], $status);
        });

        foreach ([
            [401, 'API key tidak sah.', 'UNAUTHORIZED'],
            [403, 'Klinik tidak berhak mengirim.', 'FORBIDDEN'],
        ] as [$status, $message, $code]) {
            $broadcast = $this->makeBroadcast(2);
            $broadcast->update(['status' => BroadcastStatus::Sending]);

            $this->runAttempt($broadcast->recipients()->orderBy('id')->first()->id);

            $this->assertSame(BroadcastStatus::Paused, $broadcast->fresh()->status, $code.' tidak menjeda campaign');
            $this->assertStringContainsString($message, (string) $broadcast->fresh()->paused_reason);
            $this->assertSame(
                2,
                $broadcast->recipients()->where('status', BroadcastRecipientStatus::Pending)->count(),
                $code.' menghanguskan penerima',
            );
        }
    }

    /**
     * Galat yang belum dikenal pun tidak boleh menghanguskan nomor: penyebab
     * paling mungkin ada di gateway, bukan di daftar penerima.
     */
    public function test_an_unclassified_error_pauses_the_campaign(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        $this->rejection(['message' => 'Something odd happened.', 'code' => 'SOMETHING_ODD'], 422);

        $broadcast = $this->makeBroadcast(2);
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        $this->runAttempt($broadcast->recipients()->orderBy('id')->first()->id);

        $this->assertSame(BroadcastStatus::Paused, $broadcast->fresh()->status);
        $this->assertSame(
            2,
            $broadcast->recipients()->where('status', BroadcastRecipientStatus::Pending)->count(),
        );
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
        $this->rejection(['message' => 'Nomor tidak valid.', 'code' => 'INVALID_PHONE_NUMBER'], 422);

        $broadcast = $this->makeBroadcast(3);
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->orderBy('id')->first();

        $this->runAttempt($recipient->id);

        $this->assertSame(BroadcastRecipientStatus::Failed, $recipient->fresh()->status);
        $this->assertStringContainsString('Nomor tidak valid', (string) $recipient->fresh()->error);
        $this->assertSame(BroadcastStatus::Sending, $broadcast->fresh()->status, 'satu nomor mati menghentikan seluruh blast');
    }

    /**
     * Gateway yang bilang "terlalu banyak" bukan berarti nomornya bermasalah.
     *
     * 429 punya kode 4xx yang sama dengan penolakan nomor, jadi tanpa
     * pemisahan ia jatuh ke cabang "nomor ditolak" dan penerimanya dihanguskan
     * permanen — padahal yang perlu justru berhenti mengirim sebentar. Jeda
     * yang diminta gateway dihormati, bukan ditebak.
     */
    public function test_being_throttled_returns_the_job_with_the_requested_delay(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'message' => 'Too many requests',
                'error' => ['code' => 'RATE_LIMIT_EXCEEDED'],
            ], 429, ['Retry-After' => '120']),
        ]);

        $broadcast = $this->makeBroadcast(3);
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->orderBy('id')->first();

        $queueJob = Mockery::mock(JobContract::class);
        $queueJob->shouldReceive('release')->once()->with(120);

        $this->runAttempt($recipient->id, $queueJob);

        $this->assertSame(
            BroadcastRecipientStatus::Pending,
            $recipient->fresh()->status,
            'penerima dihanguskan padahal gatewaynya cuma menahan laju',
        );
    }

    /**
     * Percobaan yang habis tidak boleh meninggalkan campaign menggantung di
     * "sedang mengirim" — tapi juga tidak boleh menghanguskan penerimanya.
     *
     * Dua penerima di laporan tersangkut "menunggu" dan campaign-nya tidak
     * pernah menutup. Yang benar: campaign dijeda dan penerimanya tetap
     * menunggu, siap dilanjutkan setelah gatewaynya pulih.
     */
    public function test_exhausted_retries_pause_the_campaign_and_keep_the_recipient_pending(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        $broadcast = $this->makeBroadcast(1);
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        (new SendBroadcastRecipientJob($recipient->id, $this->tenant->id))
            ->failed(new \RuntimeException('Gateway tidak menjawab.'));

        $broadcast->refresh();

        $this->assertSame(
            BroadcastRecipientStatus::Pending,
            $recipient->fresh()->status,
            'penerima dihanguskan padahal belum tentu nomornya yang salah',
        );
        $this->assertSame(BroadcastStatus::Paused, $broadcast->status);
        $this->assertStringContainsString('Gateway tidak menjawab.', (string) $broadcast->paused_reason);
    }
}
