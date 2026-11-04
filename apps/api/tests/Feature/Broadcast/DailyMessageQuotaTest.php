<?php

namespace Tests\Feature\Broadcast;

use App\Actions\Inbox\RecordOutboundMessageAction;
use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Enums\ConversationChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\Conversation;
use App\Models\Patient;
use App\Models\Tenant;
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
        $this->configureWhatsappGateway();

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => ['messageId' => 'uuid-ok', 'status' => 'QUEUED'],
            ], 202),
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

    /**
     * Pesan keluar yang sudah berangkat hari ini, dari jalur mana pun
     * (broadcast, balasan inbox, chatbot, pengingat).
     */
    private function alreadySent(int $count, ?Carbon $at = null, string $phone = '628111111000'): void
    {
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'contact_phone' => $phone,
            'channel' => ConversationChannel::Gateway,
            'last_message_at' => now(),
        ]);

        foreach (range(1, $count) as $i) {
            $conversation->messages()->create([
                'direction' => MessageDirection::Out,
                'body' => 'Halo '.$i,
                'status' => MessageStatus::Sent,
                'sent_at' => $at ?? now()->subHour(),
            ]);
        }
    }

    private function received(int $count): void
    {
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'contact_phone' => '628111111999',
            'channel' => ConversationChannel::Gateway,
            'last_message_at' => now(),
        ]);

        foreach (range(1, $count) as $i) {
            $conversation->messages()->create([
                'direction' => MessageDirection::In,
                'body' => 'Pesan masuk '.$i,
                'status' => MessageStatus::Sent,
                'sent_at' => now()->subHour(),
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

    /**
     * Semua jalur keluar — balasan inbox, chatbot, dan pengingat booking —
     * mencatat pesannya di tabel yang sama, jadi tidak ada yang lolos dari
     * hitungan jatah.
     */
    public function test_every_recorded_outbound_message_counts_whatever_its_path(): void
    {
        $this->actingAsClinicUser();
        config(['broadcast.daily_limit' => 10]);

        foreach (['Balasan inbox', 'Balasan chatbot', 'Pengingat booking'] as $i => $body) {
            app(RecordOutboundMessageAction::class)->handle(
                $this->tenant->id,
                '62811111100'.$i,
                $body,
                ConversationChannel::Gateway,
            );
        }

        $this->getJson($this->tenantUrl('broadcasts/dashboard'))
            ->assertOk()
            ->assertJsonPath('data.quota.used', 3);
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

    /**
     * Balasan pasien bukan kiriman klinik. Menghitungnya membuat jatah habis
     * lebih cepat padahal nomornya belum mengirim apa pun.
     */
    public function test_inbound_messages_do_not_count(): void
    {
        $this->actingAsClinicUser();
        config(['broadcast.daily_limit' => 10]);

        $this->received(3);

        $this->getJson($this->tenantUrl('broadcasts/dashboard'))
            ->assertOk()
            ->assertJsonPath('data.quota.used', 0);
    }

    /** Jatah milik nomor klinik ini, bukan nomor klinik lain. */
    public function test_another_tenants_messages_do_not_count(): void
    {
        $this->actingAsClinicUser();
        config(['broadcast.daily_limit' => 10]);

        $other = Tenant::create([
            'name' => 'Klinik Lain',
            'slug' => 'klinik-lain',
            'phone' => '08110000001',
            'status' => 'active',
        ]);

        $conversation = Conversation::create([
            'tenant_id' => $other->id,
            'contact_phone' => '628777777000',
            'channel' => ConversationChannel::Gateway,
            'last_message_at' => now(),
        ]);

        foreach (range(1, 4) as $i) {
            $conversation->messages()->create([
                'direction' => MessageDirection::Out,
                'body' => 'Halo '.$i,
                'status' => MessageStatus::Sent,
                'sent_at' => now()->subHour(),
            ]);
        }

        $this->getJson($this->tenantUrl('broadcasts/dashboard'))
            ->assertOk()
            ->assertJsonPath('data.quota.used', 0);
    }

    /** Klinik boleh punya batasnya sendiri, menimpa angka bawaan platform. */
    public function test_a_clinic_can_carry_its_own_limit(): void
    {
        $this->actingAsClinicUser();
        config(['broadcast.daily_limit' => 100]);
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
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/messages'));
    }

    /**
     * Jatah harian milik nomor pengirim, bukan milik satu campaign. Campaign
     * lain yang masih jalan ikut ditahan, bukan dibiarkan menabrak gateway.
     */
    public function test_hitting_the_limit_pauses_every_sending_campaign_of_the_clinic(): void
    {
        $this->actingAsClinicUser();
        config(['broadcast.daily_limit' => 1]);
        $this->alreadySent(1);

        $other = Tenant::create([
            'name' => 'Klinik Lain',
            'slug' => 'klinik-lain',
            'phone' => '08110000001',
            'status' => 'active',
        ]);

        $untouched = Broadcast::create([
            'tenant_id' => $other->id,
            'title' => 'PROMO KLINIK LAIN',
            'message' => 'Halo',
            'audience' => 'all',
            'status' => BroadcastStatus::Sending,
        ]);

        $first = $this->makeBroadcast(2);
        $second = $this->makeBroadcast(1);
        $first->update(['status' => BroadcastStatus::Sending]);
        $second->update(['status' => BroadcastStatus::Sending]);

        $this->runJob($first->recipients()->orderBy('id')->first()->id);

        foreach ([$first, $second] as $broadcast) {
            $fresh = $broadcast->fresh();

            $this->assertSame(BroadcastStatus::Paused, $fresh->status);
            $this->assertStringContainsString('Jatah kirim hari ini sudah penuh', (string) $fresh->paused_reason);
            $this->assertNotNull($fresh->resume_after);
            $this->assertTrue($fresh->resume_after->greaterThan(now()->endOfDay()));
        }

        $this->assertSame(BroadcastStatus::Sending, $untouched->fresh()->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/messages'));
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
