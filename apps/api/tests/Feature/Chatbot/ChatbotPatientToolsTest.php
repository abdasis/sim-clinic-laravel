<?php

namespace Tests\Feature\Chatbot;

use App\Enums\BookingStatus;
use App\Enums\ClinicRole;
use App\Models\Booking;
use App\Models\Patient;
use App\Models\Promo;
use App\Models\Service;
use App\Support\ChatTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Tool yang berpihak pada satu pasien: melihat jadwalnya dan membatalkannya,
 * plus promo yang boleh disebut.
 */
class ChatbotPatientToolsTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'whatsapp' => '6281234567890',
        ]);
    }

    private function bookingFor(Patient $patient, string $when = '+3 days', BookingStatus $status = BookingStatus::Confirmed): Booking
    {
        return Booking::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'service_id' => Service::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'start_at' => now()->modify($when),
            'end_at' => now()->modify($when)->addHour(),
            'status' => $status,
        ]);
    }

    public function test_a_patient_sees_only_their_own_upcoming_bookings(): void
    {
        $mine = $this->bookingFor($this->patient);
        $this->bookingFor(Patient::factory()->create(['tenant_id' => $this->tenant->id]));
        $this->bookingFor($this->patient, '-3 days');
        $this->bookingFor($this->patient, '+5 days', BookingStatus::Cancelled);

        $result = ChatTools::execute('list_my_bookings', [], $this->patient);

        $this->assertCount(1, $result);
        $this->assertSame($mine->id, $result[0]['booking_id']);
    }

    /** Nomor asing tidak boleh bisa mengintip jadwal siapa pun. */
    public function test_an_unknown_number_gets_no_bookings(): void
    {
        $this->bookingFor($this->patient);

        $result = ChatTools::execute('list_my_bookings', [], null);

        $this->assertSame(__('chatbot.booking_patient_unknown'), $result['error']);
    }

    public function test_a_patient_can_cancel_their_own_booking(): void
    {
        $booking = $this->bookingFor($this->patient);

        $result = ChatTools::execute('cancel_my_booking', ['booking_id' => $booking->id], $this->patient);

        $this->assertTrue($result['cancelled']);
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    /**
     * Penjaga terpenting: nomor id booking gampang ditebak, dan tanpa
     * pemeriksaan kepemilikan siapa pun bisa membatalkan jadwal orang lain
     * hanya dengan menyebut angka.
     */
    public function test_a_patient_cannot_cancel_someone_elses_booking(): void
    {
        $other = $this->bookingFor(Patient::factory()->create(['tenant_id' => $this->tenant->id]));

        $result = ChatTools::execute('cancel_my_booking', ['booking_id' => $other->id], $this->patient);

        $this->assertFalse($result['cancelled']);
        $this->assertSame(__('chatbot.booking_not_yours'), $result['reason']);
        $this->assertSame(BookingStatus::Confirmed, $other->fresh()->status);
    }

    /** Kunjungan yang sudah dikerjakan tidak bisa dibatalkan surut. */
    public function test_a_finished_booking_cannot_be_cancelled(): void
    {
        $booking = $this->bookingFor($this->patient, '+2 days', BookingStatus::Done);

        $result = ChatTools::execute('cancel_my_booking', ['booking_id' => $booking->id], $this->patient);

        $this->assertFalse($result['cancelled']);
        $this->assertSame(__('chatbot.booking_not_cancellable'), $result['reason']);
        $this->assertSame(BookingStatus::Done, $booking->fresh()->status);
    }

    public function test_only_running_promos_are_offered(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Facial Retinol']);

        $running = Promo::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Promo Berjalan',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
            'status' => 'active',
        ]);
        $running->items()->create([
            'tenant_id' => $this->tenant->id,
            'promotable_type' => 'service',
            'promotable_id' => $service->id,
        ]);

        Promo::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Promo Kedaluwarsa',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subWeek(),
            'status' => 'active',
        ]);

        $result = ChatTools::execute('get_active_promos', [], null);

        $this->assertCount(1, $result);
        $this->assertSame('Promo Berjalan', $result[0]['name']);
        $this->assertSame([['type' => 'service', 'name' => 'Facial Retinol']], $result[0]['targets']);
    }
}
