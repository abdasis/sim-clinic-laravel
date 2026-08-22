<?php

namespace Tests\Feature\Chatbot;

use App\Actions\Chatbot\CheckAvailabilityAction;
use App\Actions\Chatbot\SearchServicesAction;
use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ChatbotSetting;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Layanan yang tidak boleh dibooking lewat chat tidak boleh sempat
 * ditawarkan jadwalnya.
 *
 * Penjagaannya dulu hanya ada di create_booking — langkah paling akhir. Jadi
 * chatbot menawarkan jam, pasien menyetujuinya, lalu baru ditolak: "layanan
 * ini belum bisa dibooking lewat chat, silakan hubungi klinik". Pasien sudah
 * telanjur merasa jadwalnya jadi.
 */
class BookableServiceGateTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function makeService(string $name): Service
    {
        return Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'price' => 80000,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
    }

    private function makeTherapist(): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Jasmin',
            'email' => 'jasmin@meba.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Therapist,
        ]);
    }

    private function slot(): string
    {
        return now()->addDay()->setTime(14, 0)->format('Y-m-d H:i');
    }

    /** Tanpa pembatasan, seluruh layanan boleh — itu keadaan bawaannya. */
    public function test_every_service_is_bookable_when_nothing_is_restricted(): void
    {
        $this->actingAsClinicUser();
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id, 'bookable_service_ids' => null]);
        $this->makeService('Facial Basic');

        $row = app(SearchServicesAction::class)->handle()[0];

        $this->assertTrue($row['bookable_via_chat']);
    }

    /** Klinik yang belum pernah menyetel chatbot pun tidak membatasi apa pun. */
    public function test_a_clinic_without_settings_restricts_nothing(): void
    {
        $this->actingAsClinicUser();
        $this->makeService('Facial Basic');

        $this->assertTrue(app(SearchServicesAction::class)->handle()[0]['bookable_via_chat']);
    }

    /** Yang dibatasi ditandai sejak daftar layanan, bukan di akhir. */
    public function test_a_restricted_service_is_flagged_in_the_service_list(): void
    {
        $this->actingAsClinicUser();
        $boleh = $this->makeService('Facial Glow');
        $this->makeService('Facial Basic');

        ChatbotSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bookable_service_ids' => [$boleh->id],
        ]);

        $rows = collect(app(SearchServicesAction::class)->handle())->keyBy('name');

        $this->assertTrue($rows['Facial Glow']['bookable_via_chat']);
        $this->assertFalse($rows['Facial Basic']['bookable_via_chat']);
    }

    /**
     * Inti perbaikannya: jamnya tidak pernah sempat ditawarkan, jadi pasien
     * tidak ditolak setelah menyetujui jadwal.
     */
    public function test_a_restricted_service_never_gets_a_slot_offered(): void
    {
        $this->actingAsClinicUser();
        $boleh = $this->makeService('Facial Glow');
        $ditolak = $this->makeService('Facial Basic');
        $jasmin = $this->makeTherapist();

        ChatbotSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bookable_service_ids' => [$boleh->id],
        ]);

        $result = app(CheckAvailabilityAction::class)->handle($ditolak->id, $jasmin->id, $this->slot());

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('Facial Basic', $result['reason']);
    }

    /** Layanan yang boleh tetap berjalan seperti biasa. */
    public function test_an_allowed_service_still_gets_its_slot(): void
    {
        $this->actingAsClinicUser();
        $boleh = $this->makeService('Facial Glow');
        $jasmin = $this->makeTherapist();

        ChatbotSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bookable_service_ids' => [$boleh->id],
        ]);

        $this->assertTrue(
            app(CheckAvailabilityAction::class)->handle($boleh->id, $jasmin->id, $this->slot())['available'],
        );
    }

    /** Daftar kosong berarti tidak dibatasi, bukan "tidak ada yang boleh". */
    public function test_an_empty_list_means_no_restriction_at_all(): void
    {
        $this->actingAsClinicUser();
        $service = $this->makeService('Facial Basic');
        $jasmin = $this->makeTherapist();

        ChatbotSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bookable_service_ids' => [],
        ]);

        $this->assertTrue(
            app(CheckAvailabilityAction::class)->handle($service->id, $jasmin->id, $this->slot())['available'],
        );
    }
}
