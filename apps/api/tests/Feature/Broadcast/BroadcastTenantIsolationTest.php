<?php

namespace Tests\Feature\Broadcast;

use App\Models\MessageTemplate;
use App\Models\ReminderRule;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Validasi `exists:` bawaan Laravel berjalan lewat query builder, bukan
 * Eloquent, jadi TenantScope tidak ikut berlaku. Yang diperiksa hanya "ID ini
 * ada", bukan "ID ini milik klinik ini" — dan ID-nya angka berurutan yang
 * gampang ditebak.
 *
 * Modul broadcast menyimpan ID layanan dan templat yang isinya berakhir di
 * WhatsApp pasien, jadi kebocoran di sini bukan sekadar data salah tampil.
 */
class BroadcastTenantIsolationTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private Tenant $lain;

    private Service $layananLain;

    private MessageTemplate $templateLain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lain = $this->createTenant('klinik-tetangga');

        $this->layananLain = Service::factory()->create([
            'tenant_id' => $this->lain->id,
            'name' => 'Facial Tetangga',
        ]);

        $this->templateLain = MessageTemplate::create([
            'tenant_id' => $this->lain->id,
            'name' => 'Templat Tetangga',
            'body' => 'Halo dari klinik sebelah',
        ]);

        $this->tenant = $this->createTenant('klinik-kita');
        $this->actingAsClinicUser();
    }

    public function test_reminder_rule_cannot_point_at_another_clinics_service(): void
    {
        $this->postJson($this->tenantUrl('reminder-rules'), [
            'service_id' => $this->layananLain->id,
            'days_after' => 30,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('service_id');

        $this->assertSame(0, ReminderRule::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_reminder_rule_cannot_use_another_clinics_template(): void
    {
        $layananSendiri = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson($this->tenantUrl('reminder-rules'), [
            'service_id' => $layananSendiri->id,
            'days_after' => 30,
            'message_template_id' => $this->templateLain->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message_template_id');
    }

    /**
     * Aturan untuk layanan sendiri tetap boleh dibuat — perbaikan isolasi
     * tidak boleh ikut menutup jalur yang sah.
     */
    public function test_rule_for_its_own_service_still_works(): void
    {
        $layananSendiri = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson($this->tenantUrl('reminder-rules'), [
            'service_id' => $layananSendiri->id,
            'days_after' => 45,
        ])->assertCreated();

        $this->assertSame(1, ReminderRule::query()->count());
    }

    /**
     * Pratinjau penerima memakai service_id untuk menyaring. Layanan klinik
     * lain akan membocorkan berapa banyak dan siapa saja pasien di sana.
     */
    public function test_audience_preview_rejects_another_clinics_service(): void
    {
        $this->getJson($this->tenantUrl(
            'broadcasts/audience-preview?audience=service&service_id='.$this->layananLain->id,
        ))
            ->assertStatus(422)
            ->assertJsonValidationErrors('service_id');
    }
}
