<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Validasi `exists:` bawaan Laravel berjalan lewat query builder, bukan
 * Eloquent, sehingga `TenantScope` tidak ikut berlaku. Tanpa penjagaan
 * tambahan, kolom relasi hanya diperiksa "ada di tabel" — bukan "milik klinik
 * ini" — dan ID-nya angka berurutan sehingga cukup ditebak.
 *
 * Akibatnya bukan sekadar salah tampil: baris keuangan satu klinik menunjuk
 * pasien klinik lain, namanya kosong di nota dan laporan, dan klinik pemilik
 * tidak bisa lagi menghapus pasien itu karena FK-nya restrict.
 *
 * Setiap jalur yang menerima ID relasi diuji di sini.
 */
class TenantRelationIsolationTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private Tenant $foreign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->foreign = $this->createTenant('klinik-lain');
    }

    public function test_transaction_rejects_a_patient_from_another_clinic(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $this->foreignPatient()->id,
            'items' => [['service_id' => $this->ownService()->id, 'qty' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_transaction_rejects_a_service_from_another_clinic(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $this->ownPatient()->id,
            'items' => [['service_id' => $this->foreignService()->id, 'qty' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.service_id');
    }

    public function test_transaction_rejects_a_booking_from_another_clinic(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $this->ownPatient()->id,
            'booking_id' => $this->foreignBooking()->id,
            'items' => [['service_id' => $this->ownService()->id, 'qty' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('booking_id');
    }

    public function test_transaction_rejects_a_performer_from_another_clinic(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $this->ownPatient()->id,
            'performer_ids' => [$this->foreignUser()->id],
            'items' => [['service_id' => $this->ownService()->id, 'qty' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('performer_ids.0');
    }

    public function test_transaction_rejects_a_seller_from_another_clinic(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $this->ownPatient()->id,
            'items' => [[
                'service_id' => $this->ownService()->id,
                'qty' => 1,
                'offered_by' => $this->foreignUser()->id,
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.offered_by');
    }

    public function test_booking_rejects_relations_from_another_clinic(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->postJson($this->tenantUrl('bookings'), [
            'patient_id' => $this->foreignPatient()->id,
            'service_id' => $this->foreignService()->id,
            'assignee_id' => $this->foreignUser(ClinicRole::Doctor)->id,
            'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['patient_id', 'service_id', 'assignee_id']);
    }

    public function test_medical_record_rejects_a_booking_from_another_clinic(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);

        $this->postJson($this->tenantUrl('medical-records'), [
            'booking_id' => $this->foreignBooking()->id,
            'anamnesis' => 'Catatan.',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('booking_id');
    }

    public function test_patient_rejects_a_referrer_from_another_clinic(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->postJson($this->tenantUrl('patients'), [
            'name' => 'Pasien Uji',
            'whatsapp' => '081200000009',
            'referred_by' => $this->foreignUser()->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('referred_by');
    }

    public function test_own_relations_are_still_accepted(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $this->ownPatient()->id,
            'items' => [['service_id' => $this->ownService()->id, 'qty' => 1]],
        ])->assertCreated();
    }

    private function ownPatient(): Patient
    {
        return Patient::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function foreignPatient(): Patient
    {
        return Patient::factory()->create(['tenant_id' => $this->foreign->id]);
    }

    private function ownService(): Service
    {
        return $this->makeService($this->tenant);
    }

    private function foreignService(): Service
    {
        return $this->makeService($this->foreign);
    }

    private function makeService(Tenant $tenant): Service
    {
        return Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Facial '.$tenant->slug,
            'price' => 100000,
            'duration_minutes' => 30,
            'status' => 'active',
        ]);
    }

    private function foreignUser(ClinicRole $role = ClinicRole::Therapist): User
    {
        return User::factory()->create([
            'tenant_id' => $this->foreign->id,
            'name' => 'Staf Klinik Lain',
            'email' => 'staf.'.uniqid().'@klinik-lain.test',
            'password' => bcrypt('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => $role,
        ]);
    }

    private function foreignBooking(): Booking
    {
        return Booking::create([
            'tenant_id' => $this->foreign->id,
            'patient_id' => $this->foreignPatient()->id,
            'service_id' => $this->foreignService()->id,
            'assignee_id' => $this->foreignUser(ClinicRole::Doctor)->id,
            'start_at' => now()->subDay(),
            'end_at' => now()->subDay()->addHour(),
            'status' => BookingStatus::Done,
        ]);
    }
}
