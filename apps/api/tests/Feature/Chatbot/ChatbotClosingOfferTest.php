<?php

namespace Tests\Feature\Chatbot;

use App\Enums\ClinicRole;
use App\Models\ChatbotSetting;
use App\Models\ChatMessage;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Tawaran bantuan penutup untuk pasien yang kembali setelah lama diam.
 *
 * Dijaga tes karena rusaknya tidak kelihatan: CTA yang hilang terbaca sebagai
 * balasan biasa, jadi tidak ada yang akan melaporkannya. Jebakannya ada di
 * urutan — jeda harus diukur sebelum pesan masuk ikut tersimpan, kalau tidak
 * yang terbaca selalu pesan barusan dan jedanya selalu nol.
 */
class ChatbotClosingOfferTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser(ClinicRole::Admin);
        ChatbotSetting::factory()->create(['tenant_id' => $this->tenant->id]);

        config([
            'services.deepseek.api_key' => 'kunci',
            'services.deepseek.base_url' => 'https://ai.uji',
            'services.deepseek.model' => 'model',
        ]);

        Http::fake(['ai.uji/*' => Http::response(['choices' => [[
            'message' => ['role' => 'assistant', 'content' => 'Baik, saya bantu ya.'],
            'finish_reason' => 'stop',
        ]]])]);
    }

    private function replyAfterSilenceOf(?int $minutes): ?string
    {
        $phone = '628'.($minutes ?? 0).'11111111';

        if ($minutes !== null) {
            ChatMessage::factory()->create([
                'tenant_id' => $this->tenant->id,
                'sender_phone' => $phone,
                'created_at' => now()->subMinutes($minutes),
            ]);
        }

        return app(ChatbotService::class)->reply($phone, 'halo');
    }

    public function test_a_patient_returning_after_a_long_silence_gets_the_offer(): void
    {
        $answer = $this->replyAfterSilenceOf(20);

        $this->assertStringContainsString(__('chatbot.closing_offer'), $answer);
        // Yang tersimpan harus sama persis dengan yang dibaca pasien.
        $this->assertSame($answer, ChatMessage::query()
            ->where('direction', 'out')
            ->latest('id')
            ->value('content'));
    }

    public function test_an_ongoing_conversation_gets_no_offer(): void
    {
        $this->assertStringNotContainsString(
            __('chatbot.closing_offer'),
            $this->replyAfterSilenceOf(2),
        );
    }

    /** Nomor baru sedang memulai, bukan kembali. */
    public function test_a_first_ever_message_gets_no_offer(): void
    {
        $this->assertStringNotContainsString(
            __('chatbot.closing_offer'),
            $this->replyAfterSilenceOf(null),
        );
    }
}
