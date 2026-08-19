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
            'payload' => $payload,
        ]);
    }

    /** @return array<string, mixed> */
    private function message(string $body = 'Halo, berapa harga facial?'): array
    {
        return ['from' => '6281234567890@c.us', 'body' => $body, 'fromMe' => false, 'hasMedia' => false];
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
