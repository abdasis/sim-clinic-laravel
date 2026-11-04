<?php

namespace Tests\Feature\Chatbot;

use App\Enums\ClinicRole;
use App\Jobs\ProcessInboundMessageJob;
use App\Models\ChatbotSetting;
use App\Models\ChatMessage;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Kenapa chatbot tidak membalas.
 *
 * Ketika chatbot membisu, yang putus hampir selalu di luar kode — saklarnya
 * mati, kunci AI belum dipasang, gateway belum disetel, sesinya terputus,
 * atau webhook tidak pernah sampai. Sebelum ini tidak satu pun terlihat dari
 * layar mana pun, dan satu-satunya cara mengetahuinya adalah membaca log
 * server yang memang tidak bisa diakses klinik.
 */
class ChatbotDiagnosticsTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    /** Dipakai stub status sesi untuk menirukan gateway yang tidak terjangkau. */
    private bool $gatewayUnreachable = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();
    }

    private function everythingConnected(string $sessionStatus = 'CONNECTED'): void
    {
        ChatbotSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->gatewaySetting();

        config([
            'whatsapp_gateway.base_url' => 'https://gateway.test',
            'services.deepseek.api_key' => 'kunci-uji',
        ]);

        // Disusun sekali di sini: Http::fake yang dipanggil dua kali
        // menambah stub, bukan menggantinya, sehingga stub pertama tetap
        // menang dan status yang hendak diuji tidak pernah terpakai.
        Http::fake([
            'gateway.test/api/v1/sessions/*/status' => fn () => $this->gatewayUnreachable
                ? throw new \RuntimeException('jaringan mati')
                : Http::response([
                    'success' => true,
                    'data' => ['sessionId' => 'klinik-uji', 'status' => $sessionStatus],
                ], 200),
            'gateway.test/*' => Http::response(['success' => true, 'data' => []], 202),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function gatewaySetting(array $overrides = []): WhatsappSetting
    {
        return WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            'gateway_tenant_key' => 'tenant-uji',
            'gateway_api_key' => 'api-uji',
            'gateway_webhook_token' => 'token-uji',
            'gateway_webhook_secret' => 'secret-uji',
            'gateway_session_id' => '11111111-1111-1111-1111-111111111111',
            ...$overrides,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnose(): array
    {
        return $this->getJson($this->tenantUrl('chatbot/diagnostics'))
            ->assertOk()->json('data');
    }

    /**
     * @return array<string, bool>
     */
    private function checks(): array
    {
        return collect($this->diagnose()['checks'])
            ->mapWithKeys(fn (array $check) => [$check['key'] => $check['ok']])
            ->all();
    }

    public function test_everything_connected_reports_healthy(): void
    {
        $this->everythingConnected();

        $this->assertTrue($this->diagnose()['healthy']);
    }

    /** Saklar mati adalah sebab paling sering, dan paling mudah diperbaiki. */
    public function test_a_switched_off_chatbot_is_named(): void
    {
        $this->everythingConnected();
        ChatbotSetting::query()->update(['is_active' => false]);

        $data = $this->diagnose();

        $this->assertFalse($data['healthy']);
        $this->assertFalse(collect($data['checks'])->firstWhere('key', 'chatbot_active')['ok']);
    }

    public function test_a_missing_ai_key_is_named(): void
    {
        $this->everythingConnected();
        config(['services.deepseek.api_key' => null]);

        $this->assertFalse($this->checks()['ai_configured']);
    }

    public function test_an_unconfigured_gateway_is_named(): void
    {
        $this->everythingConnected();
        config(['whatsapp_gateway.base_url' => null]);

        $this->assertFalse($this->checks()['gateway_configured']);
    }

    /** Sesi yang terputus adalah sebab yang paling sulit ditebak sendiri. */
    public function test_a_disconnected_session_is_named(): void
    {
        $this->everythingConnected(sessionStatus: 'STOPPED');

        $this->assertFalse($this->checks()['session_connected']);
    }

    /** Gateway yang tidak bisa dihubungi dilaporkan, bukan menjatuhkan halaman. */
    public function test_an_unreachable_gateway_does_not_break_the_page(): void
    {
        $this->everythingConnected();
        $this->gatewayUnreachable = true;

        $this->assertFalse($this->checks()['session_connected']);
    }

    /** Tanpa rahasia webhook, pesan pasien tidak pernah sampai ke sistem. */
    public function test_a_missing_webhook_secret_is_named(): void
    {
        $this->everythingConnected();
        WhatsappSetting::query()->update(['gateway_webhook_secret' => null]);

        $this->assertFalse($this->checks()['webhook_configured']);
    }

    /** Tiap mata rantai yang putus membawa langkah berikutnya, bukan cuma tanda silang. */
    public function test_every_broken_link_carries_a_next_step(): void
    {
        $broken = collect($this->diagnose()['checks'])->where('ok', false);

        $this->assertGreaterThan(0, $broken->count());
        $broken->each(function (array $check): void {
            $this->assertNotNull($check['hint'], $check['key'].' tidak menjelaskan langkah berikutnya');
            $this->assertStringNotContainsString('chatbot.diagnostics', (string) $check['hint']);
        });
    }

    /** Yang sehat tidak perlu penjelasan; petunjuk hanya menambah bising. */
    public function test_a_healthy_link_carries_no_hint(): void
    {
        $this->everythingConnected();

        collect($this->diagnose()['checks'])
            ->where('ok', true)
            ->each(fn (array $check) => $this->assertNull($check['hint']));
    }

    /**
     * Kapan pesan terakhir masuk dan kapan terakhir dibalas: dua angka yang
     * langsung memisahkan "webhook tidak sampai" dari "sampai tapi tidak
     * dijawab".
     */
    public function test_the_last_inbound_and_reply_times_are_reported(): void
    {
        $this->everythingConnected();

        ChatMessage::create([
            'tenant_id' => $this->tenant->id,
            'sender_phone' => '6281234567890',
            'direction' => 'in',
            'content' => 'halo',
            'role' => 'user',
        ]);

        $data = $this->diagnose();

        $this->assertNotNull($data['last_inbound_at']);
        $this->assertNull($data['last_reply_at']);
    }

    /**
     * Gateway yang belum siap tidak boleh tetap memanggil penyedia AI:
     * balasannya langsung dibuang, dan itu terjadi pada setiap pesan masuk
     * selama gatewaynya putus.
     */
    public function test_no_ai_call_is_made_when_the_reply_cannot_be_delivered(): void
    {
        ChatbotSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        config(['services.deepseek.api_key' => 'kunci-uji']);

        // Tanpa setelan gateway sama sekali: balasan tidak akan sampai.
        Http::fake();

        (new ProcessInboundMessageJob($this->tenant->id, '6281234567890', 'halo'))->handle();

        Http::assertNothingSent();
        $this->assertSame(0, ChatMessage::query()->count());
    }

    /** Kasir tidak berkepentingan membuka diagnosa chatbot. */
    public function test_a_role_without_chatbot_access_is_refused(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->getJson($this->tenantUrl('chatbot/diagnostics'))->assertForbidden();
    }
}
