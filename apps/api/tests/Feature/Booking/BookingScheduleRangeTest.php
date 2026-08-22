<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Rentang jadwal yang diminta layar booking, termasuk tampilan sebulan.
 *
 * Layar jadwal kini terbuka dalam tampilan bulanan: yang pertama ditanyakan
 * admin adalah seberapa padat bulan ini, bukan siapa jam berapa hari ini.
 * Rentang selebar itu punya dua ujung yang gampang meleset — booking sore di
 * hari terakhir, dan hari titipan dari bulan tetangga yang mengisi baris
 * pertama kalender.
 */
class BookingScheduleRangeTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private Service $service;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = $this->actingAsClinicUser();
        $this->service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
            'price' => 300000,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
    }

    private function makeBooking(string $startAt, string $name = 'Ani Lestari'): Booking
    {
        return Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create([
                'tenant_id' => $this->tenant->id,
                'name' => $name,
            ])->id,
            'service_id' => $this->service->id,
            'assignee_id' => $this->staff->id,
            'start_at' => $startAt,
            'end_at' => now()->parse($startAt)->addHour(),
            'status' => BookingStatus::Pending,
        ]);
    }

    private function schedule(string $from, string $to, string $view = 'month')
    {
        return $this->getJson($this->tenantUrl(
            "bookings/schedule?from={$from}&to={$to}&view={$view}",
        ));
    }

    /** Tampilan bulanan diterima, bukan ditolak sebagai nilai asing. */
    public function test_the_month_view_is_accepted(): void
    {
        $this->schedule('2026-07-27', '2026-09-06')->assertOk();
    }

    public function test_an_unknown_view_is_still_rejected(): void
    {
        $this->schedule('2026-08-01', '2026-08-31', 'tahunan')
            ->assertStatus(422)
            ->assertJsonValidationErrors('view');
    }

    /**
     * Sebulan penuh terambil sekaligus, dari hari titipan di baris pertama
     * sampai yang di baris terakhir — keduanya ikut terlihat di kalender.
     */
    public function test_a_month_range_covers_the_neighbouring_days_too(): void
    {
        $this->makeBooking('2026-07-28 10:00:00', 'Titipan Awal');
        $this->makeBooking('2026-08-15 10:00:00', 'Tengah Bulan');
        $this->makeBooking('2026-09-02 10:00:00', 'Titipan Akhir');

        $names = collect(
            $this->schedule('2026-07-27', '2026-09-06')->assertOk()->json('data'),
        )->pluck('patient_name');

        $this->assertCount(3, $names);
        $this->assertContains('Titipan Awal', $names);
        $this->assertContains('Titipan Akhir', $names);
    }

    /**
     * Ujung yang paling mudah hilang: booking sore di hari terakhir rentang.
     * `to` datang sebagai tanggal, jadi tanpa endOfDay ia terpotong tengah
     * malam dan jadwal sore itu lenyap dari layar tanpa pesan galat.
     */
    public function test_an_evening_booking_on_the_last_day_is_not_dropped(): void
    {
        $this->makeBooking('2026-09-06 19:30:00', 'Sore Terakhir');

        $this->schedule('2026-07-27', '2026-09-06')
            ->assertOk()
            ->assertJsonPath('data.0.patient_name', 'Sore Terakhir');
    }

    /** Di luar rentang tetap di luar: sehari sesudahnya tidak ikut terbawa. */
    public function test_a_booking_past_the_range_stays_out(): void
    {
        $this->makeBooking('2026-09-07 09:00:00', 'Lewat Sehari');

        $this->schedule('2026-07-27', '2026-09-06')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /** Booking batal tidak memenuhi kalender dengan jadwal yang tidak jadi. */
    public function test_cancelled_bookings_stay_out_of_the_calendar(): void
    {
        $this->makeBooking('2026-08-15 10:00:00', 'Tetap Jadi');
        $this->makeBooking('2026-08-16 10:00:00', 'Batal')
            ->update(['status' => BookingStatus::Cancelled]);

        $this->schedule('2026-07-27', '2026-09-06')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.patient_name', 'Tetap Jadi');
    }

    /** Rentang terbalik ditolak, bukan diam-diam mengembalikan kosong. */
    public function test_a_backwards_range_is_rejected(): void
    {
        $this->schedule('2026-09-06', '2026-07-27')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }
}
