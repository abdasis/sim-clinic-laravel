<?php

namespace Tests\Feature\Chatbot;

use App\Jobs\ProcessInboundMessageJob;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pintu masuk pesan WhatsApp. Route-nya publik, jadi yang diuji di sini bukan
 * kecerdasannya melainkan penjaganya: siapa yang boleh masuk, dan pesan mana
 * yang memang tidak perlu diproses.
 */
class ChatbotWebhookTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const TOKEN = 'token-uji';

    /** Nomor klinik pada envelope webhook; penerima yang sah bagi pesan masuk. */
    private const ME = '628110000000@c.us';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        config(['waha.webhook_token' => self::TOKEN]);

        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);

        Queue::fake();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hit(array $payload, string $token = self::TOKEN, string $session = 'klinik-uji')
    {
        return $this->postJson('/api/whatsapp/webhook/'.$token, [
            'session' => $session,
            'me' => ['id' => self::ME],
            'payload' => $payload,
        ]);
    }

    /** @return array<string, mixed> */
    private function message(string $body = 'Halo, berapa harga facial?'): array
    {
        return [
            'from' => '6281234567890@c.us',
            'to' => self::ME,
            'body' => $body,
            'fromMe' => false,
            'hasMedia' => false,
        ];
    }

    public function test_a_valid_message_is_queued(): void
    {
        $this->hit($this->message())->assertOk();

        Queue::assertPushed(
            ProcessInboundMessageJob::class,
            fn (ProcessInboundMessageJob $job) => $job->tenantId === $this->tenant->id
                && $job->senderPhone === '6281234567890'
                && $job->body === 'Halo, berapa harga facial?',
        );
    }

    /**
     * Status WhatsApp tiba lewat webhook yang sama dengan chat biasa.
     *
     * Pernah lolos di produksi: orang yang memasang status kabar duka menerima
     * balasan bela sungkawa pribadi dari klinik, padahal ia tidak pernah
     * mengirim pesan apa pun. Nomornya ikut terbawa lewat kolom alt, jadi
     * pemeriksaan nomor saja tidak cukup — alamat obrolannya yang harus
     * diperiksa.
     */
    public function test_a_whatsapp_status_is_never_answered(): void
    {
        $this->hit([
            ...$this->message('Innalillahi, turut berduka cita.'),
            'from' => 'status@broadcast',
            'participant' => '6281234567890@c.us',
            '_data' => ['key' => [
                'remoteJid' => 'status@broadcast',
                'participant' => '6281234567890@s.whatsapp.net',
                'remoteJidAlt' => '6281234567890@s.whatsapp.net',
            ]],
        ])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_a_group_message_is_never_answered(): void
    {
        $this->hit([
            ...$this->message('Halo semua'),
            'from' => '120363042123456789@g.us',
            'participant' => '6281234567890@c.us',
            '_data' => ['key' => [
                'remoteJid' => '120363042123456789@g.us',
                'participant' => '6281234567890@s.whatsapp.net',
                'remoteJidAlt' => '6281234567890@s.whatsapp.net',
            ]],
        ])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_a_channel_post_is_never_answered(): void
    {
        $this->hit([
            ...$this->message('Promo terbaru!'),
            'from' => '120363042123456789@newsletter',
        ])->assertOk();

        Queue::assertNothingPushed();
    }

    /**
     * Kolom alt hanya sah sebagai pengganti LID. Dipakai di luar itu, payload
     * mana pun bisa menyelundupkan nomor orang yang tidak menghubungi klinik.
     */
    public function test_the_alt_number_only_stands_in_for_a_lid(): void
    {
        $this->hit([
            ...$this->message(),
            'from' => '239959873220620@lid',
            '_data' => ['key' => ['remoteJidAlt' => '6281234567890@s.whatsapp.net']],
        ])->assertOk();

        Queue::assertPushed(
            ProcessInboundMessageJob::class,
            fn (ProcessInboundMessageJob $job) => $job->senderPhone === '6281234567890',
        );
    }

    /**
     * Nomor pada kolom alt tidak boleh menggeser lawan bicara yang sebenarnya.
     */
    public function test_a_direct_chat_answers_the_number_that_wrote(): void
    {
        $this->hit([
            ...$this->message(),
            'from' => '6289999999999@c.us',
            '_data' => ['key' => ['remoteJidAlt' => '6281234567890@s.whatsapp.net']],
        ])->assertOk();

        Queue::assertPushed(
            ProcessInboundMessageJob::class,
            fn (ProcessInboundMessageJob $job) => $job->senderPhone === '6289999999999',
        );
    }

    /** Token salah tidak boleh membocorkan bahwa route-nya memang ada. */
    public function test_a_wrong_token_is_not_found(): void
    {
        $this->hit($this->message(), 'token-palsu')->assertNotFound();

        Queue::assertNothingPushed();
    }

    /**
     * Token kosong berarti belum disetel. Kalau keadaan itu dianggap "tanpa
     * penjaga", webhook terbuka untuk siapa saja di setiap server yang lupa
     * mengisi env-nya.
     */
    public function test_an_unset_token_closes_the_webhook(): void
    {
        config(['waha.webhook_token' => null]);

        $this->postJson('/api/whatsapp/webhook/', [])->assertNotFound();
        $this->hit($this->message(), '')->assertNotFound();

        Queue::assertNothingPushed();
    }

    /**
     * Gateway mengirim ulang apa pun yang tidak dibalas 200, jadi pesan yang
     * memang tidak perlu diproses tetap harus dijawab 200 — bukan galat.
     */
    public function test_messages_that_need_no_answer_are_accepted_without_a_job(): void
    {
        $this->hit($this->message(), session: 'sesi-asing')->assertOk();
        $this->hit([...$this->message(), 'fromMe' => true])->assertOk();
        $this->hit([...$this->message(), 'hasMedia' => true])->assertOk();
        $this->hit([...$this->message(), 'body' => '   '])->assertOk();
        $this->hit([...$this->message(), 'from' => 'bukan-nomor'])->assertOk();

        Queue::assertNothingPushed();
    }
}
