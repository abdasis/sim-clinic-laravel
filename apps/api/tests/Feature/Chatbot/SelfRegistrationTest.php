<?php

namespace Tests\Feature\Chatbot;

use App\Actions\Chatbot\RegisterPatientAction;
use App\Models\ChatbotSetting;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pendaftaran mandiri menulis ke tabel pasien tanpa ada admin yang meninjau,
 * jadi yang diuji di sini bukan jalan bahagianya melainkan pagarnya.
 */
class SelfRegistrationTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const SENDER = '6281299990001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        RateLimiter::clear('chatbot-register:'.$this->tenant->id.'|'.self::SENDER);
    }

    public function test_a_patient_can_register_themselves_when_the_clinic_allows_it(): void
    {
        ChatbotSetting::factory()->selfRegistration()->create(['tenant_id' => $this->tenant->id]);

        $result = $this->register(['name' => 'Budi Santoso', 'birth_date' => '1995-01-05', 'gender' => 'male']);

        $this->assertNull($result['error']);
        $this->assertSame('Budi Santoso', $result['patient']?->name);
        $this->assertDatabaseHas('patients', [
            'name' => 'Budi Santoso',
            'whatsapp' => self::SENDER,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** Nomor adalah satu-satunya bukti kepemilikan; ia tidak boleh dari AI. */
    public function test_the_sender_number_wins_over_anything_the_ai_supplies(): void
    {
        ChatbotSetting::factory()->selfRegistration()->create(['tenant_id' => $this->tenant->id]);

        $result = $this->register([
            'name' => 'Budi Santoso',
            'whatsapp' => '6289999999999',
            'phone' => '6289999999999',
        ]);

        $this->assertSame(self::SENDER, $result['patient']?->whatsapp);
        $this->assertDatabaseMissing('patients', ['whatsapp' => '6289999999999']);
    }

    public function test_registration_is_refused_while_the_toggle_is_off(): void
    {
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);

        $result = $this->register(['name' => 'Budi Santoso']);

        $this->assertNull($result['patient']);
        $this->assertSame(__('chatbot.registration_disabled'), $result['error']);
        $this->assertDatabaseCount('patients', 0);
    }

    /** Tanpa admin yang meninjau, nomor ganda diblokir — bukan diperingatkan. */
    public function test_an_existing_number_is_blocked_instead_of_duplicated(): void
    {
        ChatbotSetting::factory()->selfRegistration()->create(['tenant_id' => $this->tenant->id]);
        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'whatsapp' => '081299990001']);

        $result = $this->register(['name' => 'Budi Santoso']);

        $this->assertNull($result['patient']);
        $this->assertSame(__('chatbot.already_registered'), $result['error']);
        $this->assertDatabaseCount('patients', 1);
    }

    public function test_a_nameless_registration_is_refused(): void
    {
        ChatbotSetting::factory()->selfRegistration()->create(['tenant_id' => $this->tenant->id]);

        $result = $this->register(['birth_date' => '1995-01-05']);

        $this->assertNull($result['patient']);
        $this->assertSame(__('chatbot.registration_name_required'), $result['error']);
    }

    public function test_a_birth_date_in_the_future_is_refused(): void
    {
        ChatbotSetting::factory()->selfRegistration()->create(['tenant_id' => $this->tenant->id]);

        $result = $this->register(['name' => 'Budi Santoso', 'birth_date' => now()->addYear()->toDateString()]);

        $this->assertNull($result['patient']);
        $this->assertSame(__('chatbot.registration_birth_date_invalid'), $result['error']);
    }

    /** Percakapan yang gagal berulang tidak boleh jadi jalan membanjiri tabel. */
    public function test_repeated_attempts_from_one_number_hit_the_rate_limit(): void
    {
        ChatbotSetting::factory()->selfRegistration()->create(['tenant_id' => $this->tenant->id]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->register(['birth_date' => 'bukan tanggal']);
        }

        $result = $this->register(['name' => 'Budi Santoso']);

        $this->assertNull($result['patient']);
        $this->assertSame(__('chatbot.registration_rate_limited'), $result['error']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{patient: ?Patient, error: ?string}
     */
    private function register(array $arguments): array
    {
        return app(RegisterPatientAction::class)->handle(self::SENDER, $arguments);
    }
}
