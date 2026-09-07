<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Patient;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pagar volume harian per nomor pengirim.
 *
 * Jeda antar pesan menjaga irama kiriman, tapi yang paling sering membuat
 * nomor WhatsApp diblokir justru banyaknya pesan dalam sehari — dan itu tidak
 * terlihat dari jeda mana pun. Beberapa campaign berurutan bisa mengeluarkan
 * ratusan pesan dari satu nomor tanpa ada yang menghitung.
 */
class DailyMessageQuotaTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tenant-nya dibuat di depan: setelan WhatsApp di bawah butuh id-nya,
        // dan actingAsClinicUser() baru membuatnya saat dipanggil.
        $this->tenant = $this->createTenant();

        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);
        WahaSetting::create(['base_url' => 'https://waha.test', 'api_key' => 'kunci']);

        Http::fake([
            'waha.test/api/sessions/*' => Http::response(['status' => 'WORKING'], 200),
            'waha.test/*' => Http::response(['id' => 'true_628@c.us'], 201),
        ]);
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
            'title' => 'PROMO SEPTEMBER',
            'message' => 'Halo {nama}',
            'audience' => 'all',
        ])->assertCreated()->json('data.id');

        return Broadcast::find($id);
    }

    /** Pesan yang sudah berangkat, dari campaign mana pun. */
    private function alreadySent(int $count, ?Carbon $at = null): void
    {
        $broadcast = $this->makeBroadcast(1);
        $patient = Patient::query()->first();

        foreach (range(1, $count) as $i) {
            BroadcastRecipient::create([
                'tenant_id' => $this->tenant->id,
                'broadcast_id' => $broadcast->id,
                'patient_id' => $patient->id,
                'name' => 'Pasien '.$i,
                'phone' => '628111111'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'message' => 'Halo',
                'status' => BroadcastRecipientStatus::Sent,
                'sent_at' => $at ?? now()->subHour(),
            ]);
        }
    }

    private function runJob(int $recipientId): void
    {
        $job = new SendBroadcastRecipientJob($recipientId, $this->tenant->id);

        $queueJob = Mockery::mock(JobContract::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('getJobId')->andReturn('1');
        $job->setJob($queueJob);

        $job->handle();
    }

    public function test_the_quota_counts_messages_already_sent_today(): void
    {
        $this->actingAsClinicUser();
        config(['broadcast.daily_limit' => 10]);
        $this->alreadySent(4);

        $this->getJson($this->tenantUrl('broadcasts/dashboard'))
            ->assertOk()
            ->assertJsonPath('data.quota.limit', 10)
            ->assertJsonPath('data.quota.used', 4)
            ->assertJsonPath('data.quota.remaining', 6);
    }

    /** Kiriman kemarin tidak ikut membebani jatah hari ini. */
    public function test_yesterdays_messages_do_not_count(): void
    {
        $this->actingAsClinicUser();
        config(['broadcast.daily_limit' => 10]);

        $this->alreadySent(1, now()->subDay());

        $this->getJson($this->tenantUrl('broadcasts/dashboard'))
            ->assertOk()
            ->assertJsonPath('data.quota.used', 0);
    }

    /** Klinik boleh punya batasnya sendiri, menimpa angka bawaan platform. */
    public function test_a_clinic_can_carry_its_own_limit(): void
    {
        $this->actingAsClinicUser();
        config(['broadcast.daily_limit' => 300]);
        WhatsappSetting::query()->first()->update(['daily_message_limit' => 50]);

        $this->getJson($this->tenantUrl('broadcasts/dashboard'))
            ->assertOk()
            ->assertJsonPath('data.quota.limit', 50);
    }

    /**
     * Jatah yang sudah penuh ditolak di depan.
     *
     * Mengantrekan ratusan job untuk gugur satu per satu tidak menolong siapa
     * pun; admin justru perlu tahu sebelum menekan kirim.
     */
    public function test_sending_is_refused_once_the_quota_is_spent(): void
    {
        $this->actingAsClinicUser();
        config(['broadcast.daily_limit' => 3]);
        $this->alreadySent(3);

        $broadcast = $this->makeBroadcast(2);

        $this->postJson($this->tenantUrl("broadcasts/{$broadcast->id}/send"))
            ->assertStatus(422)
            ->assertJsonPath('message', __('broadcast.daily_limit_reached', ['limit' => 3]));
    }

    /**
     * Jatah yang habis di tengah blast menjeda campaign-nya, tidak
     * menghanguskan sisanya.
     */
    public function test_hitting_the_limit_mid_blast_pauses_until_tomorrow(): void
    {
        $this->actingAsClinicUser();
        config(['broadcast.daily_limit' => 3]);
        $this->alreadySent(3);

        $broadcast = $this->makeBroadcast(2);
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->orderBy('id')->first();

        $this->runJob($recipient->id);

        $broadcast->refresh();

        $this->assertSame(BroadcastStatus::Paused, $broadcast->status);
        $this->assertSame(BroadcastRecipientStatus::Pending, $recipient->fresh()->status);
        $this->assertNotNull($broadcast->resume_after);
        $this->assertTrue(
            $broadcast->resume_after->greaterThan(now()->endOfDay()),
            'dilanjutkan masih di hari yang sama, jadi jatahnya tetap penuh',
        );
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/sendText'));
    }

    /** Selama jatahnya masih ada, pesannya berangkat seperti biasa. */
    public function test_messages_still_go_out_while_the_quota_holds(): void
    {
        $this->actingAsClinicUser();
        config(['broadcast.daily_limit' => 10]);
        $this->alreadySent(2);

        $broadcast = $this->makeBroadcast(2);
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->orderBy('id')->first();

        $this->runJob($recipient->id);

        $this->assertSame(BroadcastRecipientStatus::Sent, $recipient->fresh()->status);
    }
}
