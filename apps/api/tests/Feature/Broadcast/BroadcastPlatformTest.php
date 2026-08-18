<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\MessageTemplate;
use App\Models\Patient;
use App\Models\ReminderRule;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use App\Support\ReminderEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Antrian, izin promosi, dan mesin pengingat — bagian yang menjangkau pasien
 * sungguhan, jadi salah kirim atau kirim ganda harus mustahil by design.
 */
class BroadcastPlatformTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function wahaReady(): void
    {
        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
        ]);

        WahaSetting::create(['base_url' => 'https://waha.test', 'api_key' => 'secret']);
    }

    private function paidVisit(Patient $patient, Service $service, string $date): Transaction
    {
        $transaction = Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-'.uniqid(),
            'subtotal' => 100000,
            'paid_amount' => 100000,
            'payment_status' => PaymentStatus::Paid,
            'issued_at' => $date.' 10:00:00',
        ]);

        $transaction->items()->create([
            'service_id' => $service->id,
            'name' => $service->name,
            'unit_price' => 100000,
            'qty' => 1,
            'subtotal' => 100000,
        ]);

        return $transaction;
    }

    public function test_send_queues_jobs_with_staggered_delay_instead_of_sync_loop(): void
    {
        Queue::fake();
        $this->actingAsClinicUser();
        $this->wahaReady();

        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'phone' => '081111111111']);
        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'phone' => '082222222222']);

        $id = $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Promo', 'message' => 'Halo {nama}', 'audience' => 'all',
        ])->assertCreated()->json('data.id');

        $this->postJson($this->tenantUrl("broadcasts/{$id}/send"))
            ->assertOk()
            ->assertJsonPath('data.queued', 2);

        Queue::assertPushed(SendBroadcastRecipientJob::class, 2);
        $this->assertSame('sending', Broadcast::find($id)->status->value);
    }

    public function test_paused_campaign_job_does_not_send(): void
    {
        $this->actingAsClinicUser();
        $this->wahaReady();
        Http::fake();

        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'phone' => '081111111111']);

        $id = $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Promo', 'message' => 'Halo', 'audience' => 'all',
        ])->assertCreated()->json('data.id');

        $broadcast = Broadcast::find($id);
        $broadcast->update(['status' => BroadcastStatus::Paused]);
        $recipient = $broadcast->recipients()->first();

        (new SendBroadcastRecipientJob($recipient->id, $this->tenant->id))->handle();

        Http::assertNothingSent();
        $this->assertSame('pending', $recipient->fresh()->status->value);
    }

    public function test_job_sends_and_closes_campaign_when_drained(): void
    {
        $this->actingAsClinicUser();
        $this->wahaReady();
        Http::fake(['waha.test/*' => Http::response(['id' => 'true_628@c.us'])]);

        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'phone' => '081111111111']);

        $id = $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Promo', 'message' => 'Halo', 'audience' => 'all',
        ])->assertCreated()->json('data.id');

        $broadcast = Broadcast::find($id);
        $broadcast->update(['status' => BroadcastStatus::Sending]);
        $recipient = $broadcast->recipients()->first();

        (new SendBroadcastRecipientJob($recipient->id, $this->tenant->id))->handle();

        $this->assertSame('sent', $recipient->fresh()->status->value);
        $this->assertSame(1, $recipient->fresh()->attempts);
        $this->assertSame('done', $broadcast->fresh()->status->value);
    }

    public function test_promo_broadcast_respects_opt_out(): void
    {
        $this->actingAsClinicUser();

        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'phone' => '081111111111', 'whatsapp_opt_in' => true]);
        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'phone' => '082222222222', 'whatsapp_opt_in' => false]);

        $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Promo', 'message' => 'Halo {nama}', 'audience' => 'all', 'kind' => 'promo',
        ])->assertCreated()->assertJsonPath('data.recipients_total', 1);
    }

    public function test_reminder_engine_creates_once_per_visit(): void
    {
        $this->actingAsClinicUser();

        $facial = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Facial']);
        // Pasien opt-out promosi — pengingat operasional tetap boleh.
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id, 'phone' => '081111111111', 'whatsapp_opt_in' => false,
        ]);

        $this->paidVisit($patient, $facial, now()->subDays(35)->toDateString());

        ReminderRule::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $facial->id,
            'days_after' => 30,
        ]);

        $first = (new ReminderEngine)->run();
        $this->assertSame(1, $first['created']);
        $this->assertSame(1, $first['recipients']);

        // Hari berikutnya dijalankan lagi: TIDAK boleh ada pengingat kedua.
        $second = (new ReminderEngine)->run();
        $this->assertSame(0, $second['created']);

        $broadcast = Broadcast::where('kind', 'reminder')->first();
        $this->assertSame('ready', $broadcast->status->value);
        $this->assertStringContainsString('Facial', $broadcast->recipients()->first()->message);
    }

    public function test_reminder_engine_skips_recent_visits(): void
    {
        $this->actingAsClinicUser();

        $facial = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id, 'phone' => '081111111111']);

        $this->paidVisit($patient, $facial, now()->subDays(10)->toDateString());

        ReminderRule::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $facial->id,
            'days_after' => 30,
        ]);

        $this->assertSame(0, (new ReminderEngine)->run()['created']);
    }

    public function test_reminder_uses_custom_template_with_double_brace_aliases(): void
    {
        $this->actingAsClinicUser();

        $facial = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Facial Glow']);
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Desi', 'phone' => '081111111111',
        ]);
        $this->paidVisit($patient, $facial, now()->subDays(40)->toDateString());

        $template = MessageTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pengingat facial',
            'body' => 'Halo Kak {{nama_pasien}}, waktunya {{nama_treatment}} lagi!',
        ]);

        ReminderRule::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $facial->id,
            'days_after' => 30,
            'message_template_id' => $template->id,
        ]);

        (new ReminderEngine)->run();

        $message = Broadcast::where('kind', 'reminder')->first()->recipients()->first()->message;
        $this->assertSame('Halo Kak Desi, waktunya Facial Glow lagi!', $message);
    }

    public function test_templates_and_reminder_rules_crud(): void
    {
        $this->actingAsClinicUser();
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $templateId = $this->postJson($this->tenantUrl('message-templates'), [
            'name' => 'Promo umum', 'body' => 'Halo {nama}!',
        ])->assertCreated()->json('data.id');

        $this->getJson($this->tenantUrl('message-templates'))->assertOk()->assertJsonCount(1, 'data');

        $this->postJson($this->tenantUrl('reminder-rules'), [
            'service_id' => $service->id, 'days_after' => 30, 'message_template_id' => $templateId,
        ])->assertCreated();

        // Layanan yang sama tidak boleh punya dua jadwal pengingat.
        $this->postJson($this->tenantUrl('reminder-rules'), [
            'service_id' => $service->id, 'days_after' => 60,
        ])->assertStatus(422);
    }

    public function test_dashboard_returns_counts(): void
    {
        $this->actingAsClinicUser();

        $this->getJson($this->tenantUrl('broadcasts/dashboard'))
            ->assertOk()
            ->assertJsonPath('data.today.sent', 0)
            ->assertJsonPath('data.active_campaigns', 0);
    }

    public function test_connection_reports_unavailable_without_waha(): void
    {
        $this->actingAsClinicUser();

        $this->getJson($this->tenantUrl('broadcasts/connection'))
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    public function test_test_message_endpoint_sends_via_client(): void
    {
        $this->actingAsClinicUser();
        $this->wahaReady();
        Http::fake(['waha.test/*' => Http::response(['id' => 'true_628@c.us'])]);

        $this->postJson($this->tenantUrl('broadcasts/test-message'), [
            'phone' => '0812-3456-7890', 'message' => 'Tes koneksi',
        ])->assertOk();

        Http::assertSent(fn ($request) => $request['chatId'] === '6281234567890@c.us');
    }
}
