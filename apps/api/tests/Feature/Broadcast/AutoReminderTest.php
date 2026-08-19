<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastStatus;
use App\Models\Booking;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\BroadcastReminderSetting;
use App\Models\MedicalRecord;
use App\Models\MessageTemplate;
use App\Models\Patient;
use App\Models\TreatmentRecord;
use App\Support\AutoReminderEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Follow-up otomatis mengirim pesan ke nomor WhatsApp pasien sungguhan tanpa
 * ada yang menekan tombol. Yang diuji karena itu: siapa yang masuk daftar,
 * siapa yang tidak, dan mustahilnya satu pasien dikirimi dua kali untuk
 * kunjungan yang sama.
 */
class AutoReminderTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function activateSetting(int $days = 30, ?MessageTemplate $template = null): BroadcastReminderSetting
    {
        return BroadcastReminderSetting::create([
            'tenant_id' => $this->tenant->id,
            'days' => $days,
            'message_template_id' => $template?->id,
            'is_active' => true,
        ]);
    }

    /**
     * Rekam medis selalu lahir dari kunjungan yang selesai — kolom booking_id
     * di tabelnya wajib isi.
     */
    private function record(Patient $patient, string $at, bool $trashed = false): MedicalRecord
    {
        $booking = Booking::factory()->done()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'assignee_id' => auth()->id(),
        ]);

        return MedicalRecord::factory()->when($trashed, fn ($f) => $f->trashed())->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'booking_id' => $booking->id,
            'author_id' => auth()->id(),
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    /** Pasien dengan satu rekam medis pada tanggal tertentu. */
    private function patientTreated(string $at, string $service = 'Facial', bool $optIn = true): Patient
    {
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'whatsapp_opt_in' => $optIn,
        ]);

        $record = $this->record($patient, $at);

        TreatmentRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'medical_record_id' => $record->id,
            'service_name' => $service,
        ]);

        return $patient;
    }

    public function test_patient_past_the_threshold_gets_a_follow_up(): void
    {
        $this->actingAsClinicUser();
        $this->activateSetting(30);

        $lama = $this->patientTreated(now()->subDays(45)->toDateTimeString(), 'Peeling');

        $result = (new AutoReminderEngine)->run();

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['recipients']);

        $broadcast = Broadcast::query()->first();
        $this->assertSame(BroadcastStatus::Ready, $broadcast->status);
        $this->assertTrue($broadcast->audience_params['auto']);
        $this->assertSame(30, $broadcast->audience_params['days']);

        $recipient = $broadcast->recipients()->first();
        $this->assertSame($lama->id, $recipient->patient_id);
        // Variabelnya diisi dari rekam medis, bukan dari transaksi POS.
        $this->assertStringContainsString('Peeling', $recipient->message);
        $this->assertStringContainsString('45 hari', $recipient->message);
    }

    public function test_patient_treated_recently_is_left_alone(): void
    {
        $this->actingAsClinicUser();
        $this->activateSetting(30);

        $this->patientTreated(now()->subDays(10)->toDateTimeString());

        $this->assertSame(0, (new AutoReminderEngine)->run()['created']);
    }

    /**
     * Rekam medis terakhir yang menentukan, bukan yang pertama. Pasien lama
     * yang baru saja datang lagi tidak boleh ikut diingatkan.
     */
    public function test_only_the_latest_record_counts(): void
    {
        $this->actingAsClinicUser();
        $this->activateSetting(30);

        $patient = $this->patientTreated(now()->subDays(90)->toDateTimeString());

        $this->record($patient, now()->subDays(3)->toDateTimeString());

        $this->assertSame(0, (new AutoReminderEngine)->run()['created']);
    }

    public function test_patient_who_opted_out_is_never_messaged(): void
    {
        $this->actingAsClinicUser();
        $this->activateSetting(30);

        $this->patientTreated(now()->subDays(60)->toDateTimeString(), optIn: false);

        $this->assertSame(0, (new AutoReminderEngine)->run()['created']);
    }

    public function test_patient_without_any_medical_record_is_not_a_target(): void
    {
        $this->actingAsClinicUser();
        $this->activateSetting(30);

        Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'whatsapp_opt_in' => true,
        ]);

        $this->assertSame(0, (new AutoReminderEngine)->run()['created']);
    }

    /**
     * Rekam yang dihapus tidak boleh menghidupkan pasien yang sebenarnya
     * belum pernah ditangani.
     */
    public function test_soft_deleted_record_does_not_count(): void
    {
        $this->actingAsClinicUser();
        $this->activateSetting(30);

        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'whatsapp_opt_in' => true,
        ]);

        $this->record($patient, now()->subDays(60)->toDateTimeString(), trashed: true);

        $this->assertSame(0, (new AutoReminderEngine)->run()['created']);
    }

    public function test_inactive_setting_sends_nothing(): void
    {
        $this->actingAsClinicUser();

        BroadcastReminderSetting::create([
            'tenant_id' => $this->tenant->id,
            'days' => 30,
            'is_active' => false,
        ]);

        $this->patientTreated(now()->subDays(60)->toDateTimeString());

        $this->assertSame(0, (new AutoReminderEngine)->run()['created']);
    }

    public function test_no_setting_at_all_sends_nothing(): void
    {
        $this->actingAsClinicUser();

        $this->patientTreated(now()->subDays(60)->toDateTimeString());

        $this->assertSame(0, (new AutoReminderEngine)->run()['created']);
    }

    /**
     * Inti fiturnya: menjalankan mesin dua kali tidak boleh membuat pasien
     * yang sama menerima dua pesan.
     */
    public function test_running_twice_does_not_message_the_same_patient_again(): void
    {
        $this->actingAsClinicUser();
        $this->activateSetting(30);

        $this->patientTreated(now()->subDays(60)->toDateTimeString());

        $this->assertSame(1, (new AutoReminderEngine)->run()['created']);
        $this->assertSame(0, (new AutoReminderEngine)->run()['created']);

        $this->assertSame(1, Broadcast::query()->count());
    }

    /**
     * Tindakan baru membuka periode pengingat baru: pasien yang sudah pernah
     * di-follow-up, lalu datang lagi dan menghilang lagi, harus terjangkau.
     *
     * Urutannya dijaga seperti di dunia nyata — dirawat, diingatkan, dirawat
     * lagi, lalu menghilang lagi. Kalau follow-up lamanya dibiarkan bertanggal
     * hari ini, ia terhitung "sesudah" tindakan yang justru terjadi
     * setelahnya, dan yang diuji bukan lagi perilaku yang sebenarnya.
     */
    public function test_a_new_treatment_opens_a_new_reminder_period(): void
    {
        $this->actingAsClinicUser();
        $this->activateSetting(30);

        $patient = $this->patientTreated(now()->subDays(120)->toDateTimeString());

        $this->assertSame(1, (new AutoReminderEngine)->run()['created']);
        $this->assertSame(0, (new AutoReminderEngine)->run()['created']);

        // Follow-up itu terkirim 90 hari lalu, bukan hari ini.
        BroadcastRecipient::query()->update([
            'created_at' => now()->subDays(90),
            'updated_at' => now()->subDays(90),
        ]);

        // Datang lagi 50 hari lalu, lalu menghilang lagi lewat ambangnya.
        $this->record($patient, now()->subDays(50)->toDateTimeString());

        $this->assertSame(1, (new AutoReminderEngine)->run()['created']);
        $this->assertSame(2, Broadcast::query()->count());
    }

    public function test_patient_without_a_valid_whatsapp_is_skipped(): void
    {
        $this->actingAsClinicUser();
        $this->activateSetting(30);

        $patient = $this->patientTreated(now()->subDays(60)->toDateTimeString());
        $patient->update(['whatsapp' => '']);

        $this->assertSame(0, (new AutoReminderEngine)->run()['created']);
    }

    public function test_custom_template_is_used_when_chosen(): void
    {
        $this->actingAsClinicUser();

        $template = MessageTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ajakan kembali',
            'body' => 'Hai {nama}, kangen {layanan_terakhir}?',
        ]);

        $this->activateSetting(30, $template);
        $this->patientTreated(now()->subDays(60)->toDateTimeString(), 'Laser');

        (new AutoReminderEngine)->run();

        $message = Broadcast::query()->first()->recipients()->first()->message;

        $this->assertSame('Hai '.Patient::query()->first()->name.', kangen Laser?', $message);
    }

    /**
     * Pasien klinik lain tidak boleh ikut terjaring — nomornya milik klinik
     * lain, dan pesannya akan datang atas nama klinik yang salah.
     */
    public function test_other_clinics_patients_are_never_included(): void
    {
        $lain = $this->createTenant('klinik-tetangga');
        $pasienLain = Patient::factory()->create([
            'tenant_id' => $lain->id,
            'whatsapp_opt_in' => true,
        ]);
        $bookingLain = Booking::factory()->done()->create([
            'tenant_id' => $lain->id,
            'patient_id' => $pasienLain->id,
        ]);
        MedicalRecord::factory()->create([
            'tenant_id' => $lain->id,
            'patient_id' => $pasienLain->id,
            'booking_id' => $bookingLain->id,
            'author_id' => $bookingLain->assignee_id,
            'created_at' => now()->subDays(90),
            'updated_at' => now()->subDays(90),
        ]);

        $this->tenant = $this->createTenant('klinik-kita');
        $this->actingAsClinicUser();
        $this->activateSetting(30);

        $this->patientTreated(now()->subDays(60)->toDateTimeString());

        (new AutoReminderEngine)->run();

        $recipients = Broadcast::query()->first()->recipients()->pluck('patient_id');

        $this->assertCount(1, $recipients);
        $this->assertNotContains($pasienLain->id, $recipients);
    }
}
