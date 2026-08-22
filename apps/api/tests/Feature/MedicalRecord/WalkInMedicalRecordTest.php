<?php

namespace Tests\Feature\MedicalRecord;

use App\Enums\BookingStatus;
use App\Enums\ClinicRole;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Rekam medis untuk pasien yang datang tanpa janji (issue #319).
 *
 * Banyak kunjungan bersifat walk-in: pasien sudah terdaftar, langsung
 * ditangani. Memaksa membuat booking formal hanya untuk menulis catatan
 * medis adalah langkah sia-sia, jadi booking kini opsional. Yang tetap
 * dijaga: pasien wajib disebut, kunjungan yang ditautkan harus benar-benar
 * milik pasien itu, dan aturan lama jalur berbooking tidak ikut longgar.
 */
class WalkInMedicalRecordTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function makePatient(string $name = 'Ani Lestari'): Patient
    {
        return Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
        ]);
    }

    private function makeBooking(BookingStatus $status, ?Patient $patient = null): Booking
    {
        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial '.uniqid(),
            'price' => 100000,
            'status' => 'active',
        ]);

        return Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => ($patient ?? $this->makePatient('Pasien Booking'))->id,
            'service_id' => $service->id,
            'assignee_id' => auth()->id(),
            'start_at' => now()->subDay(),
            'end_at' => now()->subDay()->addHour(),
            'status' => $status,
        ]);
    }

    public function test_doctor_writes_a_record_straight_from_a_patient(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $patient = $this->makePatient();

        $response = $this->postJson($this->tenantUrl('medical-records'), [
            'patient_id' => $patient->id,
            'anamnesis' => 'Pasien datang tanpa janji, mengeluh kulit kemerahan.',
            'allergy_history' => 'Tidak ada alergi diketahui.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.booking_id', null)
            ->assertJsonPath('data.patient_name', 'Ani Lestari')
            ->assertJsonPath('data.allergy_history', 'Tidak ada alergi diketahui.');

        $this->assertSame(1, MedicalRecord::query()->count());
        $this->assertNull(MedicalRecord::first()->booking_id);
    }

    /** Walk-in berulang wajar; catatan kedua tidak boleh ditolak. */
    public function test_a_patient_may_have_several_records_without_a_booking(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $patient = $this->makePatient();

        foreach (['Kunjungan pertama.', 'Kunjungan kedua.', 'Kunjungan ketiga.'] as $note) {
            $this->postJson($this->tenantUrl('medical-records'), [
                'patient_id' => $patient->id,
                'anamnesis' => $note,
            ])->assertCreated();
        }

        $this->assertSame(3, MedicalRecord::where('patient_id', $patient->id)->count());
    }

    /** Jalur lama tidak ikut longgar: pasien mengikuti kunjungannya. */
    public function test_the_booking_path_still_works_and_owns_the_patient(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $booking = $this->makeBooking(BookingStatus::Done);

        $this->postJson($this->tenantUrl('medical-records'), [
            'booking_id' => $booking->id,
            'anamnesis' => 'Kontrol setelah facial.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.patient_id', $booking->patient_id);
    }

    /** Kunjungan boleh disertai pasiennya, asal orangnya sama. */
    public function test_sending_the_matching_patient_alongside_a_booking_is_accepted(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $booking = $this->makeBooking(BookingStatus::Done);

        $this->postJson($this->tenantUrl('medical-records'), [
            'booking_id' => $booking->id,
            'patient_id' => $booking->patient_id,
            'anamnesis' => 'Kontrol setelah facial.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $booking->patient_id);
    }

    /**
     * Penjagaan terpenting jalur ganda: catatan klinis tidak boleh mendarat
     * di berkas orang lain karena pasien dan kunjungannya tidak cocok.
     */
    public function test_a_patient_who_does_not_own_the_booking_is_rejected(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $booking = $this->makeBooking(BookingStatus::Done);
        $orangLain = $this->makePatient('Budi Santoso');

        $this->postJson($this->tenantUrl('medical-records'), [
            'booking_id' => $booking->id,
            'patient_id' => $orangLain->id,
            'anamnesis' => 'Catatan nyasar.',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');

        $this->assertSame(0, MedicalRecord::query()->count());
    }

    public function test_a_record_needs_either_a_patient_or_a_booking(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);

        $this->postJson($this->tenantUrl('medical-records'), [
            'anamnesis' => 'Tanpa pasien maupun kunjungan.',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');
    }

    /** Aturan lama jalur berbooking tetap berlaku. */
    public function test_an_unfinished_booking_is_still_refused(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $booking = $this->makeBooking(BookingStatus::Pending);

        $this->postJson($this->tenantUrl('medical-records'), [
            'booking_id' => $booking->id,
            'anamnesis' => 'Terlalu cepat.',
        ])->assertStatus(422);

        $this->assertSame(0, MedicalRecord::query()->count());
    }

    public function test_one_booking_still_gets_only_one_record(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $booking = $this->makeBooking(BookingStatus::Done);
        $payload = ['booking_id' => $booking->id, 'anamnesis' => 'Catatan pertama.'];

        $this->postJson($this->tenantUrl('medical-records'), $payload)->assertCreated();
        $this->postJson($this->tenantUrl('medical-records'), $payload)->assertStatus(422);

        $this->assertSame(1, MedicalRecord::query()->count());
    }

    /** Pasien klinik lain tidak bisa dititipi catatan. */
    public function test_a_patient_from_another_clinic_is_rejected(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $other = $this->createTenant('klinik-lain');
        $foreign = Patient::factory()->create(['tenant_id' => $other->id]);

        app()->instance('tenant', $this->tenant);

        $this->postJson($this->tenantUrl('medical-records'), [
            'patient_id' => $foreign->id,
            'anamnesis' => 'Pasien tetangga.',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');
    }

    /** Asal-usul catatan harus terbaca saat riwayat klinis ditelusuri. */
    public function test_the_audit_log_says_the_visit_was_unscheduled(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $patient = $this->makePatient();

        $this->postJson($this->tenantUrl('medical-records'), [
            'patient_id' => $patient->id,
            'anamnesis' => 'Walk-in.',
        ])->assertCreated();

        $activity = Activity::where('event', 'medical_record.created')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('Ani Lestari', $activity->description);
        $this->assertStringContainsString('tanpa kunjungan terjadwal', $activity->description);
    }

    /** Pasien maupun kunjungan tidak bisa dipindah setelah catatan dibuat. */
    public function test_neither_patient_nor_booking_can_be_moved_afterwards(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $patient = $this->makePatient();

        $id = $this->postJson($this->tenantUrl('medical-records'), [
            'patient_id' => $patient->id,
            'anamnesis' => 'Walk-in.',
        ])->assertCreated()->json('data.id');

        $this->patchJson($this->tenantUrl("medical-records/{$id}"), [
            'patient_id' => $this->makePatient('Orang Lain')->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');
    }

    /** Catatan walk-in tetap muncul di riwayat pasiennya. */
    public function test_a_walk_in_record_appears_in_the_patient_history(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $patient = $this->makePatient();

        $this->postJson($this->tenantUrl('medical-records'), [
            'patient_id' => $patient->id,
            'anamnesis' => 'Walk-in.',
        ])->assertCreated();

        $this->getJson($this->tenantUrl("patients/{$patient->id}/medical-records"))
            ->assertOk()
            ->assertJsonPath('data.0.patient_id', $patient->id)
            ->assertJsonPath('data.0.booking_id', null);
    }
}
