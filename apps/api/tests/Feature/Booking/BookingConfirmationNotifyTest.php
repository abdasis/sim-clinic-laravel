<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Jobs\SendBookingConfirmationJob;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\CompanyProfileSetting;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pasien dikabari begitu kliniknya menekan konfirmasi di dasbor.
 *
 * Sebelumnya konfirmasi hanya mengubah status di layar staf; pasien yang
 * menunggu kepastian tidak menerima apa pun dan kerap menanyakannya lagi.
 *
 * Yang dijaga selain terkirimnya: konfirmasi tidak boleh gagal gara-gara
 * WhatsApp, dan kabar "jadwal Anda pasti" tidak boleh berangkat untuk
 * booking yang keburu dibatalkan.
 */
class BookingConfirmationNotifyTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function gatewayConnected(): void
    {
        $this->configureWhatsappGateway();

        Http::fake([
            'gateway.test/*' => Http::response([
                'success' => true,
                'data' => ['messageId' => 'uuid-konfirmasi', 'status' => 'QUEUED'],
            ], 202),
        ]);
    }

    private function makeBooking(BookingStatus $status = BookingStatus::Pending, string $whatsapp = '081234567890'): Booking
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
                'whatsapp' => $whatsapp,
            ])->id,
            'service_id' => $service->id,
            'assignee_id' => auth()->id(),
            'start_at' => now()->addDay()->setTime(10, 0),
            'end_at' => now()->addDay()->setTime(11, 0),
            'status' => $status,
        ]);
    }

    private function confirm(Booking $booking): void
    {
        $this->patchJson($this->tenantUrl("bookings/{$booking->id}/status"), ['status' => 'confirmed'])
            ->assertOk();
    }

    /** Menekan konfirmasi di dasbor mengantrekan kabarnya. */
    public function test_confirming_from_the_dashboard_queues_a_notification(): void
    {
        Queue::fake();
        $this->actingAsClinicUser();
        $booking = $this->makeBooking();

        $this->confirm($booking);

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        Queue::assertPushed(
            SendBookingConfirmationJob::class,
            fn (SendBookingConfirmationJob $job) => $job->bookingId === $booking->id
                && $job->tenantId === $this->tenant->id,
        );
    }

    /** Status lain tidak ikut mengabari apa pun. */
    public function test_other_status_changes_do_not_notify(): void
    {
        Queue::fake();
        $this->actingAsClinicUser();
        $booking = $this->makeBooking();

        $this->patchJson($this->tenantUrl("bookings/{$booking->id}/status"), ['status' => 'cancelled'])
            ->assertOk();

        Queue::assertNotPushed(SendBookingConfirmationJob::class);
    }

    /** Isi pesannya menyebut nama klinik dari pengaturan, bukan nama pendaftaran. */
    public function test_the_message_says_the_schedule_is_confirmed(): void
    {
        $this->actingAsClinicUser();
        $this->gatewayConnected();
        CompanyProfileSetting::create([
            'tenant_id' => $this->tenant->id,
            'site_name' => ['id' => 'Meba Clinic Aesthetic'],
        ]);

        $booking = $this->makeBooking(BookingStatus::Confirmed);

        (new SendBookingConfirmationJob($this->tenant->id, $booking->id))->handle();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/v1/messages/send')) {
                return false;
            }

            return $request['to'] === '6281234567890'
                && str_contains($request['text'], 'Ani Lestari')
                && str_contains($request['text'], 'konfirmasi')
                && str_contains($request['text'], 'Meba Clinic Aesthetic')
                && str_contains($request['text'], 'Facial Glow')
                && str_contains($request['text'], '10:00');
        });
    }

    /** Durasi tetap tidak disebut ke pasien. */
    public function test_the_message_never_mentions_the_end_time(): void
    {
        $this->actingAsClinicUser();
        $this->gatewayConnected();
        $booking = $this->makeBooking(BookingStatus::Confirmed);

        (new SendBookingConfirmationJob($this->tenant->id, $booking->id))->handle();

        Http::assertSent(fn ($request) => ! str_contains($request->url(), '/api/v1/messages/send')
            || (! str_contains($request['text'], '11:00') && ! str_contains($request['text'], 'menit')));
    }

    /**
     * Konfirmasi yang keburu dicabut tidak boleh tetap dikabari: memberi tahu
     * pasien bahwa jadwalnya pasti, padahal barusan dibatalkan, lebih buruk
     * daripada tidak mengabari sama sekali.
     */
    public function test_a_booking_no_longer_confirmed_is_not_announced(): void
    {
        $this->actingAsClinicUser();
        $this->gatewayConnected();
        $booking = $this->makeBooking(BookingStatus::Cancelled);

        (new SendBookingConfirmationJob($this->tenant->id, $booking->id))->handle();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/v1/messages/send'));
    }

    /**
     * Nomor yang tidak bisa dibaca dilewati diam-diam, bukan bikin job gagal
     * berulang — datanya memang tidak cukup, mengulanginya tidak menolong.
     */
    public function test_a_patient_with_an_unusable_number_is_skipped(): void
    {
        $this->actingAsClinicUser();
        $this->gatewayConnected();
        $booking = $this->makeBooking(BookingStatus::Confirmed, whatsapp: '-');

        (new SendBookingConfirmationJob($this->tenant->id, $booking->id))->handle();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/v1/messages/send'));
    }

    /**
     * Penjagaan terpenting: konfirmasinya sendiri harus tetap berhasil walau
     * WhatsApp klinik sedang mati. Staf sudah menekan tombolnya.
     */
    public function test_confirming_still_succeeds_when_whatsapp_is_down(): void
    {
        $this->actingAsClinicUser();
        $booking = $this->makeBooking();

        // Tidak ada setelan WAHA sama sekali: gateway belum siap.
        $this->confirm($booking);

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);

        // Job-nya tetap jalan tanpa melempar; ia hanya mencatat dan berhenti.
        (new SendBookingConfirmationJob($this->tenant->id, $booking->id))->handle();
    }

    public function test_a_sent_confirmation_is_recorded_in_the_audit_log(): void
    {
        $this->actingAsClinicUser();
        $this->gatewayConnected();
        $booking = $this->makeBooking(BookingStatus::Confirmed);

        (new SendBookingConfirmationJob($this->tenant->id, $booking->id))->handle();

        $activity = Activity::where('event', 'booking.confirmation_sent')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('Ani Lestari', $activity->description);
    }
}
