<?php

namespace Tests\Feature\Chatbot;

use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\ChatbotSetting;
use App\Models\ChatMessage;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Percakapan chatbot dari sisi yang bisa dikendalikan: AI dipalsukan, yang
 * diuji adalah apa yang dilakukan sistem terhadap jawabannya — tool mana yang
 * jalan, apa yang tersimpan, dan apa yang tidak boleh terjadi.
 */
class ChatbotConversationTest extends TestCase
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

    /** Balasan biasa tanpa tool. */
    private static function text(string $content): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']]];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function toolCall(string $name, array $arguments = []): array
    {
        return ['choices' => [[
            'message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call-1',
                    'type' => 'function',
                    'function' => ['name' => $name, 'arguments' => json_encode($arguments)],
                ]],
            ],
            'finish_reason' => 'tool_calls',
        ]]];
    }

    private function reply(string $message = 'halo'): ?string
    {
        return app(ChatbotService::class)->reply(self::SENDER, $message);
    }

    public function test_an_inactive_chatbot_stays_silent(): void
    {
        ChatbotSetting::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);
        Http::fake();

        $this->assertNull($this->reply());
        Http::assertNothingSent();
    }

    /** Belum pernah disetel sama sekali juga berarti diam. */
    public function test_an_unconfigured_chatbot_stays_silent(): void
    {
        Http::fake();

        $this->assertNull($this->reply());
        Http::assertNothingSent();
    }

    public function test_it_answers_and_stores_both_sides_of_the_conversation(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        Http::fake(['ai.uji/*' => Http::response(self::text('Halo, ada yang bisa dibantu?'))]);

        $this->assertSame('Halo, ada yang bisa dibantu?', $this->reply('halo'));

        $this->assertDatabaseHas('chat_messages', ['direction' => 'in', 'role' => 'user', 'content' => 'halo']);
        $this->assertDatabaseHas('chat_messages', ['direction' => 'out', 'role' => 'assistant', 'content' => 'Halo, ada yang bisa dibantu?']);
    }

    /**
     * Inti anti-halusinasi: harga yang disebut chatbot harus datang dari tool,
     * dan tool itu harus benar-benar membaca layanan klinik ini.
     */
    public function test_a_tool_call_is_executed_and_its_result_is_fed_back(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Facial Retinol', 'price' => 150000]);

        Http::fakeSequence()
            ->push(self::toolCall('search_services', ['keyword' => 'Facial']))
            ->push(self::text('Facial Retinol Rp150.000.'));

        $this->assertSame('Facial Retinol Rp150.000.', $this->reply('harga facial?'));

        $tool = ChatMessage::query()->where('role', 'tool')->first();

        $this->assertNotNull($tool, 'Hasil tool wajib tersimpan untuk penelusuran.');
        $this->assertSame('search_services', $tool->tool_name);
        $this->assertStringContainsString('Facial Retinol', $tool->content);
    }

    /**
     * Riwayat nomor ini ikut jadi konteks, riwayat nomor lain tidak.
     *
     * Keduanya diperiksa sekaligus: memeriksa kebocoran saja akan tetap hijau
     * seandainya riwayatnya tidak pernah terkirim sama sekali.
     */
    public function test_history_reaches_the_ai_for_this_sender_only(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);

        ChatMessage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sender_phone' => self::SENDER,
            'content' => 'PERCAKAPAN SEBELUMNYA',
        ]);
        ChatMessage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sender_phone' => '6289999999999',
            'content' => 'RAHASIA TETANGGA',
        ]);

        Http::fake(['ai.uji/*' => Http::response(self::text('Baik.'))]);

        $this->reply('halo');

        Http::assertSent(function ($request): bool {
            $sent = json_encode($request->data());

            $this->assertStringContainsString('PERCAKAPAN SEBELUMNYA', $sent);
            $this->assertStringNotContainsString('RAHASIA TETANGGA', $sent);

            return true;
        });
    }

    /** Model yang terjebak meminta tool terus tidak boleh menghabiskan kuota. */
    public function test_an_endless_tool_loop_is_cut_off(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        Http::fake(['ai.uji/*' => Http::response(self::toolCall('search_services'))]);

        $this->assertSame(__('chatbot.fallback'), $this->reply('halo'));
        Http::assertSentCount(5);
    }

    public function test_booking_via_chat_creates_a_booking_for_the_registered_patient(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);

        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ibu Sinta',
            // Ditulis berspasi seperti kebiasaan admin — pencocokan nomornya
            // harus tetap mengenali pengirim yang sama.
            'whatsapp' => '0812 3456 7890',
        ]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'duration_minutes' => 60]);
        $therapist = $this->staff();

        Http::fakeSequence()
            ->push(self::toolCall('create_booking', [
                'service_id' => $service->id,
                'assignee_id' => $therapist->id,
                'start_at' => now()->addDay()->format('Y-m-d H:i'),
            ]))
            ->push(self::text('Booking Anda sudah tercatat.'));

        $this->assertSame('Booking Anda sudah tercatat.', $this->reply('mau booking besok'));

        $this->assertDatabaseHas('bookings', [
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'assignee_id' => $therapist->id,
        ]);

        $this->assertDatabaseHas('audit_logs', ['event' => 'booking.created_via_chatbot']);
    }

    /** Nomor asing tidak boleh bisa membuat jadwal atas nama siapa pun. */
    public function test_booking_is_refused_for_an_unregistered_number(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        Http::fakeSequence()
            ->push(self::toolCall('create_booking', [
                'service_id' => $service->id,
                'assignee_id' => $this->staff()->id,
                'start_at' => now()->addDay()->format('Y-m-d H:i'),
            ]))
            ->push(self::text('Nomor Anda belum terdaftar.'));

        $this->reply('mau booking');

        $this->assertSame(0, Booking::query()->count());
    }

    /** Layanan di luar daftar yang dipilih admin tidak boleh lolos. */
    public function test_booking_is_refused_for_a_service_outside_the_allowed_list(): void
    {
        $allowed = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $other = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        ChatbotSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bookable_service_ids' => [$allowed->id],
        ]);

        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'whatsapp' => self::SENDER]);

        Http::fakeSequence()
            ->push(self::toolCall('create_booking', [
                'service_id' => $other->id,
                'assignee_id' => $this->staff()->id,
                'start_at' => now()->addDay()->format('Y-m-d H:i'),
            ]))
            ->push(self::text('Layanan itu belum bisa dibooking lewat chat.'));

        $this->reply('mau booking');

        $this->assertSame(0, Booking::query()->count());
    }

    private function staff(): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Terapis Uji',
            'email' => 'terapis-chat@uji.test',
            'password' => bcrypt('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Therapist,
        ]);
    }
}
