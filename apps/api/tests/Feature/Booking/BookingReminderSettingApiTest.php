<?php

namespace Tests\Feature\Booking;

use App\Enums\ClinicRole;
use App\Models\BookingReminderSetting;
use App\Models\MessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Jarak waktu pengingat sebelum jadwal — satu setelan untuk seluruh klinik.
 *
 * Yang dijaga di sini: klinik yang belum pernah mengaturnya tetap menerima
 * bentuk yang sama (bukan null yang harus ditebak frontend), batas jaraknya
 * ditegakkan, dan template milik klinik lain tidak bisa dipinjam.
 */
class BookingReminderSettingApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    /**
     * Belum diatur bukan keadaan galat: formulirnya butuh bentuk yang sama
     * supaya tidak perlu menebak di sisi frontend.
     */
    public function test_unset_setting_returns_a_usable_default_shape(): void
    {
        $this->actingAsClinicUser();

        $this->getJson($this->tenantUrl('bookings/reminder-settings'))
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.offset_minutes', 60)
            ->assertJsonPath('data.message_template_id', null)
            ->assertJsonPath('data.template_name', null);
    }

    public function test_admin_saves_the_reminder_setting(): void
    {
        $this->actingAsClinicUser();
        $template = MessageTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pengingat H-1',
            'body' => 'Halo {nama}, jadwal Anda besok pukul {jam}.',
        ]);

        $this->putJson($this->tenantUrl('bookings/reminder-settings'), [
            'is_active' => true,
            'offset_minutes' => 1440,
            'message_template_id' => $template->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.offset_minutes', 1440)
            ->assertJsonPath('data.template_name', 'Pengingat H-1');

        $this->assertSame(1, BookingReminderSetting::query()->count());
    }

    /** Menyimpan dua kali menimpa setelan yang sama, bukan menumpuk baris. */
    public function test_saving_twice_updates_the_same_row(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('bookings/reminder-settings'), [
            'is_active' => true,
            'offset_minutes' => 60,
        ])->assertOk();

        $this->putJson($this->tenantUrl('bookings/reminder-settings'), [
            'is_active' => false,
            'offset_minutes' => 120,
        ])
            ->assertOk()
            ->assertJsonPath('data.offset_minutes', 120);

        $this->assertSame(1, BookingReminderSetting::query()->count());
    }

    /**
     * Batas atasnya seminggu: lebih jauh dari itu pengingatnya sudah
     * terlupakan lagi sebelum harinya tiba.
     */
    public function test_offset_beyond_a_week_is_rejected(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('bookings/reminder-settings'), [
            'is_active' => true,
            'offset_minutes' => 10081,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('offset_minutes');

        $this->putJson($this->tenantUrl('bookings/reminder-settings'), [
            'is_active' => true,
            'offset_minutes' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('offset_minutes');
    }

    /** Template milik klinik lain tidak boleh dipinjam. */
    public function test_a_template_from_another_clinic_is_rejected(): void
    {
        $this->actingAsClinicUser();
        $other = $this->createTenant('klinik-lain');
        $foreign = MessageTemplate::create([
            'tenant_id' => $other->id,
            'name' => 'Template Tetangga',
            'body' => 'Halo',
        ]);

        // createTenant memindahkan tenant aktif; dikembalikan supaya
        // permintaannya benar-benar datang dari klinik yang sedang diuji.
        app()->instance('tenant', $this->tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

        $this->putJson($this->tenantUrl('bookings/reminder-settings'), [
            'is_active' => true,
            'offset_minutes' => 60,
            'message_template_id' => $foreign->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message_template_id');
    }

    /** Kasir tidak mengurus jadwal, jadi tidak boleh menyentuh setelannya. */
    public function test_cashier_cannot_read_or_change_the_setting(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->getJson($this->tenantUrl('bookings/reminder-settings'))->assertForbidden();

        $this->putJson($this->tenantUrl('bookings/reminder-settings'), [
            'is_active' => true,
            'offset_minutes' => 60,
        ])->assertForbidden();
    }
}
