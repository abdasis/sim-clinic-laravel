<?php

namespace Tests\Feature\Broadcast;

use App\Enums\ClinicRole;
use App\Models\BroadcastReminderSetting;
use App\Models\MessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pengaturan follow-up otomatis menentukan siapa yang dikirimi pesan tanpa
 * campur tangan manusia, jadi yang dijaga: siapa yang boleh mengubahnya, dan
 * nilai apa saja yang boleh masuk.
 */
class AutoReminderSettingApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_setting_starts_empty_and_inactive(): void
    {
        $this->actingAsClinicUser();

        $this->getJson($this->tenantUrl('broadcasts/auto-reminder'))
            ->assertOk()
            ->assertJsonPath('data.days', null)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.message_template_id', null);
    }

    public function test_admin_saves_and_reads_back_the_setting(): void
    {
        $this->actingAsClinicUser();

        $template = MessageTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ajakan kembali',
            'body' => 'Halo {nama}',
        ]);

        $this->putJson($this->tenantUrl('broadcasts/auto-reminder'), [
            'days' => 45,
            'message_template_id' => $template->id,
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.days', 45)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.template_name', 'Ajakan kembali');

        $this->getJson($this->tenantUrl('broadcasts/auto-reminder'))
            ->assertJsonPath('data.days', 45)
            ->assertJsonPath('data.template_name', 'Ajakan kembali');
    }

    /** Satu baris per klinik: menyimpan dua kali tidak menumpuk. */
    public function test_saving_twice_updates_the_same_row(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('broadcasts/auto-reminder'), ['days' => 30])->assertOk();
        $this->putJson($this->tenantUrl('broadcasts/auto-reminder'), ['days' => 60])->assertOk();

        $this->assertSame(1, BroadcastReminderSetting::query()->count());
        $this->assertSame(60, BroadcastReminderSetting::query()->first()->days);
    }

    public function test_days_outside_the_allowed_range_are_rejected(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('broadcasts/auto-reminder'), ['days' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('days');

        $this->putJson($this->tenantUrl('broadcasts/auto-reminder'), ['days' => 731])
            ->assertStatus(422)
            ->assertJsonValidationErrors('days');

        $this->putJson($this->tenantUrl('broadcasts/auto-reminder'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('days');
    }

    /**
     * Templat milik klinik lain akan terkirim ke pasien klinik ini atas nama
     * klinik ini. `exists:` biasa lolos karena berjalan lewat query builder,
     * tanpa TenantScope.
     */
    public function test_template_from_another_clinic_is_rejected(): void
    {
        $lain = $this->createTenant('klinik-tetangga');
        $templateLain = MessageTemplate::create([
            'tenant_id' => $lain->id,
            'name' => 'Milik tetangga',
            'body' => 'Halo',
        ]);

        $this->tenant = $this->createTenant('klinik-kita');
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('broadcasts/auto-reminder'), [
            'days' => 30,
            'message_template_id' => $templateLain->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message_template_id');
    }

    public function test_cashier_cannot_change_the_setting(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->putJson($this->tenantUrl('broadcasts/auto-reminder'), ['days' => 30])
            ->assertForbidden();

        $this->assertNull(BroadcastReminderSetting::query()->first());
    }

    /** Perubahan pengaturan yang menyentuh pasien harus tercatat. */
    public function test_saving_is_written_to_the_audit_log(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('broadcasts/auto-reminder'), [
            'days' => 30,
            'is_active' => true,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'broadcast.auto_reminder.created',
            'subject_type' => 'broadcast_reminder_setting',
        ]);

        $this->putJson($this->tenantUrl('broadcasts/auto-reminder'), [
            'days' => 90,
            'is_active' => false,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'broadcast.auto_reminder.updated',
        ]);
    }
}
