<?php

namespace Tests\Feature\Chatbot;

use App\Actions\Chatbot\GetClinicInfoAction;
use App\Actions\Chatbot\ListPatientBookingsAction;
use App\Actions\Chatbot\SearchServicesAction;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CompanyProfileSetting;
use App\Models\Patient;
use App\Models\Service;
use App\Support\ClinicIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Dua hal yang dilihat pasien langsung dari WhatsApp.
 *
 * Nama klinik: `tenants.name` adalah nama pendaftaran, sering masih singkatan
 * atau nama sementara. Yang dipelihara klinik adalah `site_name` di
 * pengaturan profil, dan itulah yang harus dipakai menyapa — kalau tidak,
 * pasien menerima dua nama berbeda dari klinik yang sama.
 *
 * Detail booking: yang ditunggu pasien adalah jam kedatangannya. Durasi
 * treatment dan jam selesai tidak perlu disebut, jadi angkanya tidak
 * dikirimkan ke AI sama sekali — larangan lewat prompt saja masih bisa
 * dilanggar model.
 */
class ClinicNameAndBookingDetailTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function setSiteName(string $name): void
    {
        CompanyProfileSetting::create([
            'tenant_id' => $this->tenant->id,
            'site_name' => ['id' => $name],
        ]);
    }

    private function makeService(): Service
    {
        return Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
            'price' => 300000,
            'duration_minutes' => 90,
            'status' => 'active',
        ]);
    }

    public function test_the_display_name_comes_from_the_profile_setting(): void
    {
        $this->actingAsClinicUser();
        $this->setSiteName('Meba Clinic Aesthetic');

        $this->assertSame('Meba Clinic Aesthetic', ClinicIdentity::displayName($this->tenant));
    }

    /** Klinik yang belum mengisi profil tetap punya nama untuk disapa. */
    public function test_it_falls_back_to_the_registration_name(): void
    {
        $this->actingAsClinicUser();

        $this->assertSame($this->tenant->name, ClinicIdentity::displayName($this->tenant));
    }

    /** Nama kosong di profil tidak boleh menghasilkan sapaan tanpa nama. */
    public function test_a_blank_profile_name_falls_back_too(): void
    {
        $this->actingAsClinicUser();
        $this->setSiteName('   ');

        $this->assertSame($this->tenant->name, ClinicIdentity::displayName($this->tenant));
    }

    /** Sapaan chatbot memakai nama yang sama dengan sidebar dan kop nota. */
    public function test_the_chatbot_greeting_uses_the_configured_name(): void
    {
        $this->actingAsClinicUser();
        $this->setSiteName('Meba Clinic Aesthetic');

        $this->getJson($this->tenantUrl('identity'))
            ->assertOk()
            ->assertJsonPath('data.name', 'Meba Clinic Aesthetic');

        $this->assertSame(
            'Meba Clinic Aesthetic',
            app(GetClinicInfoAction::class)->handle()['name'],
        );
    }

    /** Jawaban harga tidak lagi membawa durasi treatment. */
    public function test_service_answers_no_longer_carry_the_duration(): void
    {
        $this->actingAsClinicUser();
        $this->makeService();

        $row = app(SearchServicesAction::class)->handle()[0];

        $this->assertSame('Facial Glow', $row['name']);
        $this->assertArrayNotHasKey('duration_minutes', $row);
    }

    /** Daftar jadwal pasien menyebut jam mulai saja. */
    public function test_booking_list_only_carries_the_start_time(): void
    {
        $this->actingAsClinicUser();
        $service = $this->makeService();
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'whatsapp' => '081234567890',
        ]);

        Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'assignee_id' => auth()->id(),
            'start_at' => now()->addDay()->setTime(14, 0),
            'end_at' => now()->addDay()->setTime(15, 30),
            'status' => BookingStatus::Pending,
        ]);

        $row = app(ListPatientBookingsAction::class)->handle($patient)[0];

        $this->assertSame(now()->addDay()->format('Y-m-d').' 14:00', $row['start_at']);
        $this->assertArrayNotHasKey('end_at', $row);
        $this->assertArrayNotHasKey('duration_minutes', $row);
    }

    /** Slot tetap dihitung penuh di server meski durasinya tidak disebut. */
    public function test_the_slot_length_is_still_honoured_when_booking(): void
    {
        $this->actingAsClinicUser();
        $service = $this->makeService();
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'assignee_id' => auth()->id(),
            'start_at' => now()->addDay()->setTime(14, 0),
            'end_at' => now()->addDay()->setTime(14, 0)->addMinutes($service->duration_minutes),
            'status' => BookingStatus::Pending,
        ]);

        $this->assertEqualsWithDelta(90, $booking->start_at->diffInMinutes($booking->end_at), 0.001);
    }
}
