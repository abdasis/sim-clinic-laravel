<?php

namespace Tests\Feature\Chatbot;

use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\ChatbotSetting;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Mendaftar lalu langsung booking dalam satu giliran percakapan.
 *
 * Pasien diresolusi sekali sebelum putaran tool dimulai dan tidak pernah
 * dibaca ulang, sehingga pasien yang baru saja mendaftar tetap dianggap
 * belum terdaftar pada panggilan berikutnya — dan dijawab "silakan daftar
 * dulu di klinik" padahal datanya sudah tersimpan. Persis di detik ia
 * hendak booking, ia justru diusir ke klinik.
 */
class RegisterThenBookTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const SENDER = '6281234567890';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser(ClinicRole::Admin);

        config([
            'services.deepseek.api_key' => 'kunci-uji',
            'services.deepseek.base_url' => 'https://ai.uji',
            'services.deepseek.model' => 'model-uji',
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function toolCall(string $name, array $arguments = []): array
    {
        return ['choices' => [[
            'message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call-'.$name,
                    'type' => 'function',
                    'function' => ['name' => $name, 'arguments' => json_encode($arguments)],
                ]],
            ],
            'finish_reason' => 'tool_calls',
        ]]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function text(string $content): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']]];
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

    /**
     * Inti bug: daftar dulu, lalu booking — keduanya di giliran yang sama.
     */
    public function test_a_patient_who_just_registered_can_book_in_the_same_turn(): void
    {
        ChatbotSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'allow_self_registration' => true,
        ]);

        $service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
            'duration_minutes' => 60,
        ]);
        $jasmin = $this->makeTherapist();
        $slot = now()->addDay()->setTime(14, 0)->format('Y-m-d H:i');

        // Giliran AI: daftarkan pasiennya, lalu langsung buatkan bookingnya.
        Http::fakeSequence()
            ->push(self::toolCall('register_patient', ['name' => 'Ani Lestari']))
            ->push(self::toolCall('create_booking', [
                'service_id' => $service->id,
                'assignee_id' => $jasmin->id,
                'start_at' => $slot,
            ]))
            ->push(self::text('Booking Ani Lestari sudah tercatat bersama Jasmin.'));

        $answer = app(ChatbotService::class)->reply(self::SENDER, 'saya mau daftar lalu booking');

        $this->assertSame('Booking Ani Lestari sudah tercatat bersama Jasmin.', $answer);

        // Yang sesungguhnya dijaga: bookingnya benar-benar jadi, bukan
        // ditolak dengan alasan pasiennya belum terdaftar.
        $patient = Patient::query()->first();
        $this->assertNotNull($patient);
        $this->assertSame('Ani Lestari', $patient->name);

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame($patient->id, $booking->patient_id);
        $this->assertSame($jasmin->id, $booking->assignee_id);
    }

    /** Nomor yang memang belum mendaftar tetap tidak bisa booking. */
    public function test_a_number_that_never_registered_still_cannot_book(): void
    {
        ChatbotSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'allow_self_registration' => true,
        ]);

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $jasmin = $this->makeTherapist();

        Http::fakeSequence()
            ->push(self::toolCall('create_booking', [
                'service_id' => $service->id,
                'assignee_id' => $jasmin->id,
                'start_at' => now()->addDay()->setTime(14, 0)->format('Y-m-d H:i'),
            ]))
            ->push(self::text('Boleh saya daftarkan dulu?'));

        app(ChatbotService::class)->reply(self::SENDER, 'mau booking');

        $this->assertSame(0, Booking::query()->count());
        $this->assertSame(0, Patient::query()->count());
    }

    /** Pasien lama tetap dikenali tanpa perlu mendaftar ulang. */
    public function test_an_already_registered_number_books_without_registering(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);

        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Budi',
            // Ditulis admin dalam ejaan nasional berspasi; gateway mengirim
            // bentuk internasional. Keduanya harus tetap bertemu.
            'whatsapp' => '0812 3456 7890',
        ]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $jasmin = $this->makeTherapist();

        Http::fakeSequence()
            ->push(self::toolCall('create_booking', [
                'service_id' => $service->id,
                'assignee_id' => $jasmin->id,
                'start_at' => now()->addDay()->setTime(14, 0)->format('Y-m-d H:i'),
            ]))
            ->push(self::text('Sudah tercatat ya.'));

        app(ChatbotService::class)->reply(self::SENDER, 'mau booking');

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame($patient->id, $booking->patient_id);
    }
}
