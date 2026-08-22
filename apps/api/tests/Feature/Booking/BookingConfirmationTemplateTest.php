<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Jobs\SendBookingConfirmationJob;
use App\Models\Booking;
use App\Models\BookingReminderSetting;
use App\Models\CompanyProfileSetting;
use App\Models\MessageTemplate;
use App\Models\Patient;
use App\Models\Service;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Teks konfirmasi booking bisa disunting klinik, bukan terkunci di kode.
 *
 * Kalimat bawaan cocok untuk kebanyakan klinik, tapi tidak untuk semua: ada
 * yang menitipkan aturan datang lebih awal, ada yang menyebut nomor lantai.
 * Sebelumnya satu-satunya cara mengubahnya adalah lewat rilis baru.
 *
 * Yang dijaga selain tersambungnya: klinik yang belum memilih tetap dapat
 * kalimat yang layak, dan template yang dihapus tidak membuat pasien
 * menerima pesan kosong.
 */
class BookingConfirmationTemplateTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function wahaConnected(): void
    {
        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);
        WahaSetting::create(['base_url' => 'https://waha.test', 'api_key' => 'kunci']);

        Http::fake([
            'waha.test/api/sessions/*' => Http::response(['status' => 'WORKING'], 200),
            'waha.test/*' => Http::response(['id' => 'true_628@c.us'], 201),
        ]);
    }

    private function makeBooking(): Booking
    {
        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
            'price' => 300000,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);

        return Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Ani Lestari',
                'whatsapp' => '081234567890',
            ])->id,
            'service_id' => $service->id,
            'assignee_id' => auth()->id(),
            'start_at' => now()->addDay()->setTime(10, 0),
            'end_at' => now()->addDay()->setTime(11, 0),
            'status' => BookingStatus::Confirmed,
        ]);
    }

    private function template(string $body, string $name = 'Konfirmasi Ramah'): MessageTemplate
    {
        return MessageTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'body' => $body,
        ]);
    }

    private function sentText(): ?string
    {
        $text = null;

        Http::assertSent(function ($request) use (&$text) {
            if (str_contains($request->url(), '/api/sendText')) {
                $text = $request['text'];
            }

            return true;
        });

        return $text;
    }

    private function settingsUrl(): string
    {
        return $this->tenantUrl('bookings/reminder-settings');
    }

    /** Inti permintaannya: teks racikan klinik yang dipakai, bukan bawaan. */
    public function test_the_clinic_template_replaces_the_built_in_text(): void
    {
        $this->actingAsClinicUser();
        $this->wahaConnected();

        BookingReminderSetting::create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
            'offset_minutes' => 60,
            'confirmation_template_id' => $this->template(
                'Halo :pasien, :layanan Anda pada :tanggal pukul :jam sudah pasti. Parkir di lantai 2 ya.',
            )->id,
        ]);

        (new SendBookingConfirmationJob($this->tenant->id, $this->makeBooking()->id))->handle();

        $text = $this->sentText();

        $this->assertStringContainsString('Parkir di lantai 2', (string) $text);
        $this->assertStringContainsString('Ani Lestari', (string) $text);
        $this->assertStringContainsString('Facial Glow', (string) $text);
        $this->assertStringContainsString('10:00', (string) $text);
        // Kalimat bawaan benar-benar tergantikan, bukan ditempel di depannya.
        $this->assertStringNotContainsString('Sampai jumpa ya', (string) $text);
    }

    /** Placeholder klinik ikut terisi, termasuk nama dari pengaturan tenant. */
    public function test_the_clinic_name_placeholder_is_filled_from_settings(): void
    {
        $this->actingAsClinicUser();
        $this->wahaConnected();
        CompanyProfileSetting::create([
            'tenant_id' => $this->tenant->id,
            'site_name' => ['id' => 'Meba Clinic Aesthetic'],
        ]);

        BookingReminderSetting::create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
            'offset_minutes' => 60,
            'confirmation_template_id' => $this->template('Konfirmasi dari :klinik untuk :pasien bersama :staf.')->id,
        ]);

        (new SendBookingConfirmationJob($this->tenant->id, $this->makeBooking()->id))->handle();

        $text = (string) $this->sentText();

        $this->assertStringContainsString('Meba Clinic Aesthetic', $text);
        // Tidak ada placeholder mentah yang lolos ke pasien.
        $this->assertStringNotContainsString(':klinik', $text);
        $this->assertStringNotContainsString(':staf', $text);
    }

    /** Klinik yang belum memilih tetap dapat kalimat bawaan, bukan pesan kosong. */
    public function test_a_clinic_without_a_choice_still_gets_the_built_in_text(): void
    {
        $this->actingAsClinicUser();
        $this->wahaConnected();

        BookingReminderSetting::create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'offset_minutes' => 60,
        ]);

        (new SendBookingConfirmationJob($this->tenant->id, $this->makeBooking()->id))->handle();

        $this->assertStringContainsString('konfirmasi', (string) $this->sentText());
    }

    /**
     * Template pengingat dan konfirmasi tidak boleh tertukar: keduanya
     * disimpan di baris setelan yang sama, jadi salah kolom akan lolos diam.
     */
    public function test_the_reminder_template_is_not_used_for_confirmations(): void
    {
        $this->actingAsClinicUser();
        $this->wahaConnected();

        BookingReminderSetting::create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'offset_minutes' => 60,
            'message_template_id' => $this->template('Ini teks pengingat.', 'Pengingat')->id,
        ]);

        (new SendBookingConfirmationJob($this->tenant->id, $this->makeBooking()->id))->handle();

        $this->assertStringNotContainsString('Ini teks pengingat', (string) $this->sentText());
    }

    /** Template yang dihapus mengembalikan teks bawaan, bukan pesan kosong. */
    public function test_deleting_the_template_falls_back_instead_of_sending_nothing(): void
    {
        $this->actingAsClinicUser();
        $this->wahaConnected();

        $template = $this->template('Halo :pasien.');
        BookingReminderSetting::create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
            'offset_minutes' => 60,
            'confirmation_template_id' => $template->id,
        ]);

        $template->delete();

        (new SendBookingConfirmationJob($this->tenant->id, $this->makeBooking()->id))->handle();

        $text = (string) $this->sentText();

        $this->assertNotSame('', trim($text));
        $this->assertStringContainsString('konfirmasi', $text);
    }

    /** Pilihannya tersimpan dan terbaca lagi lewat layar pengaturan. */
    public function test_the_choice_is_saved_and_read_back(): void
    {
        $this->actingAsClinicUser();
        $template = $this->template('Halo :pasien.');

        $this->putJson($this->settingsUrl(), [
            'is_active' => true,
            'offset_minutes' => 60,
            'confirmation_template_id' => $template->id,
        ])->assertOk();

        $this->getJson($this->settingsUrl())
            ->assertOk()
            ->assertJsonPath('data.confirmation_template_id', $template->id)
            ->assertJsonPath('data.confirmation_template_name', 'Konfirmasi Ramah');
    }

    /** Klinik yang belum mengatur apa pun tetap menerima bentuk yang sama. */
    public function test_an_unset_clinic_reads_a_null_choice(): void
    {
        $this->actingAsClinicUser();

        $this->getJson($this->settingsUrl())
            ->assertOk()
            ->assertJsonPath('data.confirmation_template_id', null)
            ->assertJsonPath('data.confirmation_template_name', null);
    }

    /**
     * Template milik klinik lain tidak bisa dipasang: teks konfirmasi tetangga
     * yang terkirim atas nama klinik ini jauh lebih buruk daripada galat form.
     */
    public function test_a_template_from_another_clinic_is_rejected(): void
    {
        $this->actingAsClinicUser();

        $other = $this->createTenant('klinik-lain');
        $foreign = MessageTemplate::create([
            'tenant_id' => $other->id,
            'name' => 'Punya Tetangga',
            'body' => 'Halo :pasien.',
        ]);

        // createTenant mengikat tenant tetangga di container; dikembalikan
        // supaya request berikutnya berjalan sebagai klinik semula.
        app()->instance('tenant', $this->tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

        $this->putJson($this->settingsUrl(), [
            'is_active' => true,
            'offset_minutes' => 60,
            'confirmation_template_id' => $foreign->id,
        ])->assertStatus(422)->assertJsonValidationErrors('confirmation_template_id');
    }
}
