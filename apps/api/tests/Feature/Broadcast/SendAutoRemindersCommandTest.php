<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastKind;
use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Booking;
use App\Models\Broadcast;
use App\Models\BroadcastReminderSetting;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Command inilah yang berjalan tanpa ada yang mengawasi tiap pagi. Yang
 * diuji: klinik mana yang dapat giliran, dan broadcast mana yang boleh
 * diantrekannya.
 */
class SendAutoRemindersCommandTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function duePatient(Tenant $tenant): Patient
    {
        $patient = Patient::factory()->create([
            'tenant_id' => $tenant->id,
            'whatsapp_opt_in' => true,
        ]);

        $booking = Booking::factory()->done()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
        ]);

        MedicalRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'booking_id' => $booking->id,
            'author_id' => $booking->assignee_id,
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ]);

        return $patient;
    }

    private function activate(Tenant $tenant, int $days = 30): void
    {
        BroadcastReminderSetting::create([
            'tenant_id' => $tenant->id,
            'days' => $days,
            'is_active' => true,
        ]);
    }

    /** Tanpa WAHA broadcast-nya tetap tersusun, menunggu dikirim manual. */
    public function test_broadcast_stays_ready_when_waha_is_not_configured(): void
    {
        Queue::fake();
        $this->tenant = $this->createTenant();
        $this->activate($this->tenant);
        $this->duePatient($this->tenant);

        $this->artisan('clinic:send-auto-reminders')->assertSuccessful();

        $broadcast = Broadcast::withoutGlobalScopes()->first();
        $this->assertNotNull($broadcast);
        $this->assertSame(BroadcastStatus::Ready, $broadcast->status);
        Queue::assertNothingPushed();
    }

    public function test_broadcast_is_queued_when_waha_is_ready(): void
    {
        Queue::fake();
        $this->tenant = $this->createTenant();
        $this->activate($this->tenant);
        $this->duePatient($this->tenant);

        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);
        WahaSetting::create(['base_url' => 'https://waha.test', 'api_key' => 'kunci']);
        // Antre hanya kalau sesinya benar-benar tersambung.
        Http::fake(['waha.test/*' => Http::response(['status' => 'WORKING'], 200)]);

        $this->artisan('clinic:send-auto-reminders')->assertSuccessful();

        $this->assertSame(
            BroadcastStatus::Sending,
            Broadcast::withoutGlobalScopes()->first()->status,
        );
        Queue::assertPushed(SendBroadcastRecipientJob::class, 1);
    }

    /**
     * Pengingat per-layanan punya command sendiri. Kalau command ini ikut
     * mengantrekannya, pesan yang sengaja ditahan admin untuk dikirim manual
     * jadi terkirim tanpa diminta.
     */
    public function test_it_does_not_queue_broadcasts_from_the_other_engine(): void
    {
        Queue::fake();
        $this->tenant = $this->createTenant();
        $this->activate($this->tenant);
        $this->duePatient($this->tenant);

        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);
        WahaSetting::create(['base_url' => 'https://waha.test', 'api_key' => 'kunci']);

        $perLayanan = Broadcast::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Pengingat Facial',
            'kind' => BroadcastKind::Reminder,
            'status' => BroadcastStatus::Ready,
            'message' => 'Halo',
            'audience' => 'service',
            'audience_params' => ['service_id' => 1],
        ]);

        $this->artisan('clinic:send-auto-reminders')->assertSuccessful();

        $this->assertSame(BroadcastStatus::Ready, $perLayanan->refresh()->status);
    }

    /** Klinik tanpa pengaturan aktif tidak boleh ikut dikirimi. */
    public function test_only_clinics_with_an_active_setting_are_processed(): void
    {
        Queue::fake();

        $aktif = $this->createTenant('klinik-aktif');
        $this->activate($aktif);
        $this->duePatient($aktif);

        $pasif = $this->createTenant('klinik-pasif');
        $this->duePatient($pasif);

        $this->artisan('clinic:send-auto-reminders')->assertSuccessful();

        $broadcasts = Broadcast::withoutGlobalScopes()->get();

        $this->assertCount(1, $broadcasts);
        $this->assertSame($aktif->id, $broadcasts->first()->tenant_id);
    }
}
