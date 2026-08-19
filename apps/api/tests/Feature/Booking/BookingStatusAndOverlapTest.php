<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingOverlapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Alur status booking hanya boleh maju: menunggu → dikonfirmasi → selesai,
 * dan pembatalan bersifat final. Bentrok jadwal hanya diperingatkan.
 */
class BookingStatusAndOverlapTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_status_moves_forward_through_allowed_transitions(): void
    {
        $this->actingAsClinicUser();
        $booking = $this->makeBooking();

        $this->patchJson($this->tenantUrl('bookings/'.$booking->id.'/status'), ['status' => 'confirmed'])
            ->assertOk();
        $this->patchJson($this->tenantUrl('bookings/'.$booking->id.'/status'), ['status' => 'done'])
            ->assertOk();

        $this->assertSame(BookingStatus::Done, $booking->fresh()->status);
    }

    public function test_done_booking_cannot_be_cancelled(): void
    {
        $this->actingAsClinicUser();
        $booking = $this->makeBooking(BookingStatus::Done);

        $this->patchJson($this->tenantUrl('bookings/'.$booking->id.'/status'), ['status' => 'cancelled'])
            ->assertStatus(422);

        $this->assertSame(BookingStatus::Done, $booking->fresh()->status);
    }

    public function test_cancelled_booking_is_final(): void
    {
        $this->actingAsClinicUser();
        $booking = $this->makeBooking(BookingStatus::Cancelled);

        foreach (['pending', 'confirmed', 'done'] as $target) {
            $this->patchJson($this->tenantUrl('bookings/'.$booking->id.'/status'), ['status' => $target])
                ->assertStatus(422);
        }

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_status_change_is_recorded_narratively_with_old_and_new(): void
    {
        $this->actingAsClinicUser();
        $booking = $this->makeBooking();

        $this->patchJson($this->tenantUrl('bookings/'.$booking->id.'/status'), ['status' => 'confirmed'])
            ->assertOk();

        $activity = Activity::where('event', 'booking.status_changed')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('Menunggu', $activity->description);
        $this->assertStringContainsString('Dikonfirmasi', $activity->description);
        $this->assertSame('pending', $activity->properties['old']['status']);
        $this->assertSame('confirmed', $activity->properties['new']['status']);
    }

    public function test_overlap_detected_for_same_assignee(): void
    {
        $this->actingAsClinicUser();
        $first = $this->makeBooking();

        // Mulai di tengah jadwal pertama, penanggung jawab sama.
        $second = $this->makeBooking(start: $first->start_at->copy()->addMinutes(30));

        $overlaps = app(BookingOverlapService::class)->detect($second);

        $this->assertCount(1, $overlaps);
        $this->assertSame($first->id, $overlaps[0]['booking_id']);
    }

    public function test_touching_schedules_do_not_overlap(): void
    {
        $this->actingAsClinicUser();
        $first = $this->makeBooking();

        // Mulai tepat saat jadwal pertama berakhir — bersinggungan, bukan bentrok.
        $second = $this->makeBooking(start: $first->end_at->copy());

        $this->assertSame([], app(BookingOverlapService::class)->detect($second));
    }

    public function test_cancelled_booking_is_ignored_by_overlap_detection(): void
    {
        $this->actingAsClinicUser();
        $first = $this->makeBooking(BookingStatus::Cancelled);
        $second = $this->makeBooking(start: $first->start_at->copy()->addMinutes(30));

        $this->assertSame([], app(BookingOverlapService::class)->detect($second));
    }

    public function test_other_assignee_does_not_overlap(): void
    {
        $this->actingAsClinicUser();
        $first = $this->makeBooking();

        $second = $this->makeBooking(
            start: $first->start_at->copy()->addMinutes(30),
            assignee: $this->makeDoctor(),
        );

        $this->assertSame([], app(BookingOverlapService::class)->detect($second));
    }

    private function makeDoctor(): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'dr. Lain',
            'email' => 'dokter.lain'.uniqid().'@'.$this->tenant->slug.'.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Doctor,
        ]);
    }

    private function makeBooking(
        BookingStatus $status = BookingStatus::Pending,
        ?Carbon $start = null,
        ?User $assignee = null,
    ): Booking {
        $start ??= now()->addDay()->startOfHour();

        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial',
            'price' => 100000,
            'status' => 'active',
        ]);

        return Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'assignee_id' => $assignee?->id ?? auth()->id(),
            'start_at' => $start,
            'end_at' => $start->copy()->addHour(),
            'status' => $status,
        ]);
    }
}
