<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingReminderStatus;
use App\Enums\BookingStatus;
use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendBookingReminderJob;
use App\Models\Booking;
use App\Models\BookingReminder;
use App\Models\BookingReminderSetting;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pengingat H-minus untuk jadwal booking.
 *
 * Yang paling dijaga di sini bukan pengirimannya, melainkan pembatalannya:
 * job tertunda tidak bisa ditarik dari antrian, jadi satu-satunya yang
 * menghentikannya adalah baris pengingat yang dibacanya saat bangun. Kalau
 * pemeriksaan itu meleset, pasien menerima pengingat untuk jadwal yang sudah
 * dibatalkan — kesalahan yang langsung terlihat olehnya.
 */
class BookingReminderTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private Patient $patient;

    private Service $service;

    private User $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        // Driver antrian saat pengujian adalah `sync`: tanpa ini, job langsung
        // jalan di detik pendaftaran booking — persis kebalikan dari yang
        // hendak diuji, yaitu apa yang terjadi saat job bangun belakangan.
        Queue::fake();

        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ibu Sinta',
            'whatsapp' => '6281234567890',
        ]);
        $this->service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Retinol',
        ]);
        $this->therapist = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Terapis Uji',
            'email' => 'terapis-reminder@uji.test',
            'password' => bcrypt('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Therapist,
        ]);
    }

    private function activate(int $offsetMinutes = 60): void
    {
        BookingReminderSetting::create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'offset_minutes' => $offsetMinutes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function book(array $overrides = []): Booking
    {
        [$booking] = app(BookingService::class)->create([
            'patient_id' => $this->patient->id,
            'service_id' => $this->service->id,
            'assignee_id' => $this->therapist->id,
            'start_at' => now()->addDay()->setTime(14, 0),
            'end_at' => now()->addDay()->setTime(15, 0),
            'remind_booking' => true,
            ...$overrides,
        ]);

        return $booking;
    }

    public function test_a_reminder_is_scheduled_one_offset_before_the_booking(): void
    {
        $this->activate(60);

        $booking = $this->book();
        $reminder = BookingReminder::query()->where('booking_id', $booking->id)->firstOrFail();

        $this->assertSame(BookingReminderStatus::Pending, $reminder->status);
        $this->assertSame(
            $booking->start_at->copy()->subHour()->format('Y-m-d H:i'),
            $reminder->remind_at->format('Y-m-d H:i'),
        );
        // Offsetnya disalin, bukan dibaca ulang saat kirim.
        $this->assertSame(60, $reminder->reminder_offset_minutes);

        Queue::assertPushed(SendBookingReminderJob::class);
    }

    /** Dua saklar harus sama-sama menyala. */
    public function test_no_reminder_when_either_switch_is_off(): void
    {
        // Setelan klinik mati.
        $this->book();
        $this->assertSame(0, BookingReminder::query()->count());

        // Penanda per booking mati.
        $this->activate();
        $this->book(['remind_booking' => false]);
        $this->assertSame(0, BookingReminder::query()->count());

        Queue::assertNothingPushed();
    }

    /** Didaftarkan mepet jadwal tetap diingatkan, sekarang juga. */
    public function test_a_booking_made_too_close_is_reminded_immediately(): void
    {
        $this->activate(60);

        $booking = $this->book([
            'start_at' => now()->addMinutes(10),
            'end_at' => now()->addMinutes(70),
        ]);

        $reminder = BookingReminder::query()->where('booking_id', $booking->id)->firstOrFail();

        $this->assertTrue($reminder->remind_at->diffInMinutes(now(), absolute: true) < 1);
    }

    /** Satu booking tidak boleh melahirkan dua baris pengingat. */
    public function test_rescheduling_keeps_a_single_reminder_row(): void
    {
        $this->activate();

        $booking = $this->book();

        app(BookingService::class)->update($booking, [
            'patient_id' => $this->patient->id,
            'service_id' => $this->service->id,
            'assignee_id' => $this->therapist->id,
            'start_at' => now()->addDays(2)->setTime(9, 0),
            'end_at' => now()->addDays(2)->setTime(10, 0),
            'remind_booking' => true,
        ]);

        $this->assertSame(1, BookingReminder::query()->count());

        $reminder = BookingReminder::query()->firstOrFail();

        $this->assertSame(BookingReminderStatus::Pending, $reminder->status);
        $this->assertSame(
            now()->addDays(2)->setTime(8, 0)->format('Y-m-d H:i'),
            $reminder->remind_at->format('Y-m-d H:i'),
        );
    }

    public function test_cancelling_the_booking_cancels_the_reminder(): void
    {
        $this->activate();

        $booking = $this->book();
        app(BookingService::class)->changeStatus($booking, BookingStatus::Cancelled->value);

        $this->assertSame(
            BookingReminderStatus::Cancelled,
            BookingReminder::query()->firstOrFail()->status,
        );
    }

    public function test_turning_the_switch_off_cancels_the_reminder(): void
    {
        $this->activate();

        $booking = $this->book();

        app(BookingService::class)->update($booking, [
            'patient_id' => $this->patient->id,
            'service_id' => $this->service->id,
            'assignee_id' => $this->therapist->id,
            'start_at' => $booking->start_at,
            'end_at' => $booking->end_at,
            'remind_booking' => false,
        ]);

        $this->assertSame(
            BookingReminderStatus::Cancelled,
            BookingReminder::query()->firstOrFail()->status,
        );
    }

    /** Jalur penuh: job benar-benar mengirim pesan berisi detail jadwalnya. */
    public function test_the_job_sends_a_message_with_the_booking_details(): void
    {
        $this->activate();
        WahaSetting::create(['base_url' => 'https://waha.uji', 'api_key' => 'kunci']);
        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);
        Http::fake(['waha.uji/*' => Http::response(['ok' => true])]);

        $booking = $this->book();
        $reminder = BookingReminder::query()->firstOrFail();

        $this->runReminder($reminder);

        Http::assertSent(function ($request) use ($booking): bool {
            $text = $request->data()['text'];

            $this->assertStringContainsString('Ibu Sinta', $text);
            $this->assertStringContainsString('Facial Retinol', $text);
            $this->assertStringContainsString('Terapis Uji', $text);
            $this->assertStringContainsString($booking->start_at->format('H:i'), $text);

            return true;
        });

        $reminder->refresh();

        $this->assertSame(BookingReminderStatus::Sent, $reminder->status);
        $this->assertNotNull($reminder->sent_at);
    }

    /** Job yang diulang tidak boleh mengirim pesan kedua. */
    public function test_a_retried_job_does_not_send_twice(): void
    {
        $this->activate();
        WahaSetting::create(['base_url' => 'https://waha.uji', 'api_key' => 'kunci']);
        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);
        Http::fake(['waha.uji/*' => Http::response(['ok' => true])]);

        $this->book();
        $reminder = BookingReminder::query()->firstOrFail();

        $this->runReminder($reminder);
        $this->runReminder($reminder);

        Http::assertSentCount(1);
    }

    /**
     * Job lama yang terlanjur antre harus berhenti sendiri setelah jadwalnya
     * diubah — inilah satu-satunya yang mencegah pasien menerima pengingat
     * untuk jam yang sudah tidak berlaku.
     */
    public function test_a_stale_job_stops_after_the_booking_was_rescheduled(): void
    {
        $this->activate();
        WahaSetting::create(['base_url' => 'https://waha.uji', 'api_key' => 'kunci']);
        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);
        Http::fake(['waha.uji/*' => Http::response(['ok' => true])]);

        $booking = $this->book();
        $reminder = BookingReminder::query()->firstOrFail();
        $stalePromise = $reminder->remind_at->toIso8601String();

        app(BookingService::class)->update($booking, [
            'patient_id' => $this->patient->id,
            'service_id' => $this->service->id,
            'assignee_id' => $this->therapist->id,
            'start_at' => now()->addDays(3)->setTime(11, 0),
            'end_at' => now()->addDays(3)->setTime(12, 0),
            'remind_booking' => true,
        ]);

        // Job lama bangun membawa janji waktu yang lama.
        (new SendBookingReminderJob($this->tenant->id, $reminder->id, $stalePromise))->handle();

        Http::assertNothingSent();
        $this->assertSame(
            BookingReminderStatus::Pending,
            BookingReminder::query()->firstOrFail()->status,
        );
    }

    private function runReminder(BookingReminder $reminder): void
    {
        (new SendBookingReminderJob(
            $this->tenant->id,
            $reminder->id,
            $reminder->fresh()->remind_at->toIso8601String(),
        ))->handle();
    }
}
