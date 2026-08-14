<?php

namespace Tests\Feature\Booking;

use App\Actions\Booking\UpdateBookingAction;
use App\Enums\BookingStatus;
use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Rekam medis terikat pada pasiennya. Setelah rekam medis ditulis, booking
 * tidak boleh dipindahkan ke pasien lain karena riwayat klinisnya akan
 * menempel pada orang yang salah.
 */
class BookingPatientImmutableTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_patient_can_be_changed_while_no_medical_record_exists(): void
    {
        $this->actingAsClinicUser();
        $doctor = $this->makeDoctor();
        $booking = $this->makeBooking();
        $other = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->putJson($this->tenantUrl('bookings/'.$booking->id), [
            'patient_id' => $other->id,
            'service_id' => $booking->service_id,
            'assignee_id' => $doctor->id,
            'start_at' => $booking->start_at->format('Y-m-d H:i:s'),
            'end_at' => $booking->end_at->format('Y-m-d H:i:s'),
        ])->assertOk();

        $this->assertSame($other->id, $booking->fresh()->patient_id);
    }

    public function test_api_rejects_patient_change_after_medical_record(): void
    {
        $this->actingAsClinicUser();
        $doctor = $this->makeDoctor();
        $booking = $this->makeBookingWithMedicalRecord();
        $other = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->putJson($this->tenantUrl('bookings/'.$booking->id), [
            'patient_id' => $other->id,
            'service_id' => $booking->service_id,
            'assignee_id' => $doctor->id,
            'start_at' => $booking->start_at->format('Y-m-d H:i:s'),
            'end_at' => $booking->end_at->format('Y-m-d H:i:s'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');

        $this->assertNotSame($other->id, $booking->fresh()->patient_id);
    }

    public function test_action_rejects_patient_change_even_when_called_directly(): void
    {
        $this->actingAsClinicUser();
        $booking = $this->makeBookingWithMedicalRecord();
        $other = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->expectException(HttpException::class);

        app(UpdateBookingAction::class)->handle($booking, ['patient_id' => $other->id]);
    }

    public function test_other_fields_remain_editable_after_medical_record(): void
    {
        $this->actingAsClinicUser();
        $doctor = $this->makeDoctor();
        $booking = $this->makeBookingWithMedicalRecord();

        $this->putJson($this->tenantUrl('bookings/'.$booking->id), [
            'patient_id' => $booking->patient_id,
            'service_id' => $booking->service_id,
            'assignee_id' => $doctor->id,
            'start_at' => $booking->start_at->format('Y-m-d H:i:s'),
            'end_at' => $booking->end_at->addHour()->format('Y-m-d H:i:s'),
        ])->assertOk();
    }

    public function test_show_exposes_has_medical_record_flag(): void
    {
        $this->actingAsClinicUser();

        $plain = $this->makeBooking();
        $withRecord = $this->makeBookingWithMedicalRecord();

        $this->getJson($this->tenantUrl('bookings/'.$plain->id))
            ->assertOk()
            ->assertJsonPath('data.has_medical_record', false);

        $this->getJson($this->tenantUrl('bookings/'.$withRecord->id))
            ->assertOk()
            ->assertJsonPath('data.has_medical_record', true);
    }

    private function makeDoctor(): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'dr. Uji',
            'email' => 'dokter.uji@'.$this->tenant->slug.'.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Doctor,
        ]);
    }

    private function makeBooking(): Booking
    {
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
            'assignee_id' => auth()->id(),
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'status' => BookingStatus::Pending,
        ]);
    }

    private function makeBookingWithMedicalRecord(): Booking
    {
        $booking = $this->makeBooking();

        MedicalRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'patient_id' => $booking->patient_id,
            'author_id' => auth()->id(),
            'subjective' => 'Keluhan awal',
        ]);

        return $booking->fresh();
    }
}
