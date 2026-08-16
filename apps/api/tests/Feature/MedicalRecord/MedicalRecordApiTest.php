<?php

namespace Tests\Feature\MedicalRecord;

use App\Enums\BookingStatus;
use App\Enums\ClinicRole;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Rekam medis hanya boleh lahir dari kunjungan yang benar-benar selesai,
 * dan setelah lahir kunjungannya tidak bisa dipindah ke pasien lain.
 */
class MedicalRecordApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_doctor_can_write_record_for_finished_booking(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $booking = $this->makeBooking(BookingStatus::Done);

        $response = $this->postJson($this->tenantUrl('medical-records'), [
            'booking_id' => $booking->id,
            'anamnesis' => 'Pasien mengeluh kulit kering sejak dua minggu lalu.',
            'skincare_history' => 'Pakai sabun wajah bebas busa dan pelembab malam.',
            'allergy_history' => 'Alergi retinol dosis tinggi.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.patient_id', $booking->patient_id);
        $response->assertJsonPath('data.allergy_history', 'Alergi retinol dosis tinggi.');

        $this->assertDatabaseHas('medical_records', [
            'booking_id' => $booking->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_unfinished_booking_cannot_be_recorded(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $booking = $this->makeBooking(BookingStatus::Pending);

        $this->postJson($this->tenantUrl('medical-records'), [
            'booking_id' => $booking->id,
            'anamnesis' => 'Catatan.',
        ])->assertStatus(422);

        $this->assertDatabaseCount('medical_records', 0);
    }

    public function test_one_booking_only_gets_one_record(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $booking = $this->makeBooking(BookingStatus::Done);

        $payload = ['booking_id' => $booking->id, 'anamnesis' => 'Catatan pertama.'];

        $this->postJson($this->tenantUrl('medical-records'), $payload)->assertCreated();
        $this->postJson($this->tenantUrl('medical-records'), $payload)->assertStatus(422);

        $this->assertDatabaseCount('medical_records', 1);
    }

    public function test_cashier_cannot_touch_medical_records(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $booking = $this->makeBooking(BookingStatus::Done);

        $this->getJson($this->tenantUrl('medical-records'))->assertForbidden();
        $this->postJson($this->tenantUrl('medical-records'), [
            'booking_id' => $booking->id,
        ])->assertForbidden();
    }

    public function test_booking_cannot_be_moved_on_update(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $record = $this->makeRecord();
        $otherBooking = $this->makeBooking(BookingStatus::Done);

        $this->patchJson($this->tenantUrl('medical-records/'.$record->id), [
            'booking_id' => $otherBooking->id,
            'anamnesis' => 'Kontrol dua minggu lagi.',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('booking_id');
    }

    public function test_soap_can_be_revised(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $record = $this->makeRecord(['anamnesis' => 'Catatan lama.']);

        $this->patchJson($this->tenantUrl('medical-records/'.$record->id), [
            'anamnesis' => 'Catatan baru.',
        ])
            ->assertOk()
            ->assertJsonPath('data.anamnesis', 'Catatan baru.');

        $this->assertSame('Catatan baru.', $record->fresh()->anamnesis);
    }

    public function test_deleted_record_disappears_from_listing(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $record = $this->makeRecord();

        $this->getJson($this->tenantUrl('medical-records'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->deleteJson($this->tenantUrl('medical-records/'.$record->id))->assertOk();

        $this->assertSoftDeleted('medical_records', ['id' => $record->id]);

        $this->getJson($this->tenantUrl('medical-records'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson($this->tenantUrl('medical-records/'.$record->id))->assertNotFound();
    }

    public function test_listing_can_be_searched_by_patient_name(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);

        $this->makeRecord([], Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Siti Rahmawati',
        ]));
        $this->makeRecord([], Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Budi Santoso',
        ]));

        $this->getJson($this->tenantUrl('medical-records?search=Rahma'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.patient_name', 'Siti Rahmawati');
    }

    public function test_patient_history_returns_records_in_order(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $older = $this->makeRecord(['created_at' => now()->subWeek()], $patient);
        $newer = $this->makeRecord(['created_at' => now()], $patient);
        $this->makeRecord();

        $response = $this->getJson(
            $this->tenantUrl('patients/'.$patient->id.'/medical-records'),
        );

        $response->assertOk();
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('data.0.id', $older->id);
        $response->assertJsonPath('data.1.id', $newer->id);
    }

    public function test_record_of_other_tenant_is_not_reachable(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $other = $this->createTenant('klinik-lain');

        $foreign = MedicalRecord::factory()->create([
            'tenant_id' => $other->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $other->id])->id,
        ]);

        // createTenant memindahkan tenant aktif; kembalikan ke tenant penguji.
        app()->instance('tenant', $this->tenant);

        $this->getJson($this->tenantUrl('medical-records/'.$foreign->id))->assertNotFound();
        $this->patchJson($this->tenantUrl('medical-records/'.$foreign->id), [
            'anamnesis' => 'Coba sunting.',
        ])->assertNotFound();
        $this->deleteJson($this->tenantUrl('medical-records/'.$foreign->id))->assertNotFound();

        $this->assertNull($foreign->fresh()->deleted_at);
    }

    public function test_patient_history_of_other_tenant_is_not_reachable(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $other = $this->createTenant('klinik-lain');
        $foreignPatient = Patient::factory()->create(['tenant_id' => $other->id]);

        app()->instance('tenant', $this->tenant);

        $this->getJson(
            $this->tenantUrl('patients/'.$foreignPatient->id.'/medical-records'),
        )->assertNotFound();
    }

    private function makeBooking(BookingStatus $status): Booking
    {
        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial',
            'price' => 100000,
            'status' => 'active',
        ]);

        return Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'service_id' => $service->id,
            'assignee_id' => auth()->id(),
            'start_at' => now()->subDay(),
            'end_at' => now()->subDay()->addHour(),
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeRecord(array $attributes = [], ?Patient $patient = null): MedicalRecord
    {
        $patient ??= Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        return MedicalRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'author_id' => auth()->id(),
            ...$attributes,
        ]);
    }
}
