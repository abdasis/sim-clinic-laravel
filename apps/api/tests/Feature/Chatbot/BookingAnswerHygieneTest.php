<?php

namespace Tests\Feature\Chatbot;

use App\Actions\Chatbot\CheckAvailabilityAction;
use App\Enums\BookingStatus;
use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\ChatbotSetting;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Support\ChatTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Tiga keluhan nyata dari lapangan, dijaga di sisi data — bukan di prompt.
 *
 * 1. Pasien meminta satu terapis, yang tersimpan orang lain. Nama yang
 *    diperiksa kini ikut pulang di hasil tool, jadi identitasnya tidak
 *    hilang di tengah percakapan dan penggantian jadi terlihat.
 * 2. Chatbot menyuruh pasien datang ke klinik untuk mendaftar, padahal
 *    kliniknya sudah membuka pendaftaran lewat chat.
 * 3. Durasi treatment bocor ke WhatsApp. Angkanya tidak dikirimkan sama
 *    sekali, karena larangan di prompt masih bisa dilanggar model.
 */
class BookingAnswerHygieneTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function makeService(int $minutes = 90): Service
    {
        return Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
            'price' => 300000,
            'duration_minutes' => $minutes,
            'status' => 'active',
        ]);
    }

    private function makeTherapist(string $name): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'email' => str($name)->slug().'@meba.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Therapist,
        ]);
    }

    /** Jam kerja klinik contoh: siang hari pasti di dalam jam operasional. */
    private function slot(): string
    {
        return now()->addDay()->setTime(14, 0)->format('Y-m-d H:i');
    }

    /**
     * (1) Nama yang diperiksa ikut pulang. Tanpa ini jawabannya hanya
     * "available: true" tanpa identitas, dan siapa pun bisa dipasang.
     */
    public function test_checking_a_named_therapist_echoes_who_was_checked(): void
    {
        $this->actingAsClinicUser();
        $service = $this->makeService();
        $jasmin = $this->makeTherapist('Jasmin');
        $this->makeTherapist('Mutia');

        $result = app(CheckAvailabilityAction::class)->handle($service->id, $jasmin->id, $this->slot());

        $this->assertTrue($result['available']);
        $this->assertSame($jasmin->id, $result['staff_id']);
        $this->assertSame('Jasmin', $result['staff_name']);
    }

    /** Yang diminta sedang penuh: namanya tetap disebut, bukan diganti diam-diam. */
    public function test_a_busy_therapist_is_named_instead_of_swapped(): void
    {
        $this->actingAsClinicUser();
        $service = $this->makeService();
        $jasmin = $this->makeTherapist('Jasmin');
        $this->makeTherapist('Mutia');

        Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'service_id' => $service->id,
            'assignee_id' => $jasmin->id,
            'start_at' => now()->addDay()->setTime(14, 0),
            'end_at' => now()->addDay()->setTime(15, 30),
            'status' => BookingStatus::Pending,
        ]);

        $result = app(CheckAvailabilityAction::class)->handle($service->id, $jasmin->id, $this->slot());

        $this->assertFalse($result['available']);
        $this->assertSame('Jasmin', $result['staff_name']);
        // Tidak ada daftar pengganti di jalur ini: menawarkannya di sini
        // membuat AI menyangka boleh langsung memakainya.
        $this->assertArrayNotHasKey('available_staff', $result);
    }

    /** (3) Jam selesai tidak pernah ikut, jadi durasinya tidak bisa dihitung. */
    public function test_the_availability_answer_never_carries_an_end_time(): void
    {
        $this->actingAsClinicUser();
        $service = $this->makeService();
        $jasmin = $this->makeTherapist('Jasmin');

        foreach ([
            app(CheckAvailabilityAction::class)->handle($service->id, $jasmin->id, $this->slot()),
            app(CheckAvailabilityAction::class)->handle($service->id, null, $this->slot()),
            app(CheckAvailabilityAction::class)->handle($service->id, $jasmin->id, 'bukan-waktu'),
        ] as $result) {
            $this->assertArrayNotHasKey('end_at', $result);
            $this->assertArrayNotHasKey('duration_minutes', $result);
        }
    }

    /** Slot tetap dihitung penuh di server meski durasinya tidak disebut. */
    public function test_the_slot_length_still_decides_the_conflict(): void
    {
        $this->actingAsClinicUser();
        $service = $this->makeService(90);
        $jasmin = $this->makeTherapist('Jasmin');

        Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'service_id' => $service->id,
            'assignee_id' => $jasmin->id,
            'start_at' => now()->addDay()->setTime(15, 0),
            'end_at' => now()->addDay()->setTime(16, 0),
            'status' => BookingStatus::Pending,
        ]);

        // Mulai 14:00 + 90 menit menabrak booking 15:00; kalau durasinya
        // diabaikan, bentrok ini akan lolos tanpa terdeteksi.
        $result = app(CheckAvailabilityAction::class)->handle($service->id, $jasmin->id, $this->slot());

        $this->assertFalse($result['available']);
    }

    /**
     * (2) Klinik yang membuka pendaftaran lewat chat tidak boleh menyuruh
     * pasiennya datang hanya untuk mendaftar.
     */
    public function test_an_unregistered_number_is_told_to_register_through_chat(): void
    {
        $this->actingAsClinicUser();
        ChatbotSetting::create([
            'tenant_id' => $this->tenant->id,
            'allow_self_registration' => true,
        ]);

        $error = ChatTools::execute('create_booking', [], null, '6281234567890')['error'];

        $this->assertStringContainsString('register_patient', $error);
        // Yang dilarang bukan katanya, melainkan arahannya: pasien tidak
        // boleh disuruh ke klinik hanya untuk bisa mendaftar.
        $this->assertStringNotContainsString('Silakan daftar dulu di klinik', $error);
    }

    /** Klinik yang menutupnya tetap mengarahkan pasien mendaftar di tempat. */
    public function test_a_clinic_without_self_registration_still_points_to_the_desk(): void
    {
        $this->actingAsClinicUser();
        ChatbotSetting::create([
            'tenant_id' => $this->tenant->id,
            'allow_self_registration' => false,
        ]);

        $error = ChatTools::execute('create_booking', [], null, '6281234567890')['error'];

        $this->assertStringContainsString('daftar dulu di klinik', $error);
    }
}
