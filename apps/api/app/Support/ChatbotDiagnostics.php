<?php

namespace App\Support;

use App\Models\ChatbotSetting;
use App\Models\ChatMessage;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Kesehatan tiap mata rantai yang membuat chatbot bisa menjawab.
 *
 * Ketika chatbot berhenti membalas, kodenya sendiri jarang jadi sebab —
 * yang putus biasanya salah satu dari lima hal di luar kode: saklarnya mati,
 * kunci AI belum dipasang, gateway WhatsApp belum disetel, sesinya terputus,
 * atau webhook tidak pernah sampai. Sebelum ini tidak satu pun terlihat dari
 * layar mana pun; satu-satunya cara mengetahuinya adalah membaca log server,
 * dan klinik tidak punya akses ke sana.
 *
 * Yang dilaporkan sengaja bukan sekadar "sehat/tidak", melainkan langkah
 * berikutnya untuk tiap mata rantai yang putus. Pemberitahuan yang tidak
 * memberi tahu apa yang harus dilakukan hanya memindahkan kebingungan.
 */
class ChatbotDiagnostics
{
    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $checks = [
            $this->chatbotActive(),
            $this->aiConfigured(),
            $this->gatewayConfigured(),
            $this->sessionConnected(),
            $this->webhookConfigured(),
        ];

        return [
            // Satu mata rantai putus sudah cukup membuat chatbot membisu, jadi
            // ringkasannya mengikuti yang terlemah, bukan rata-ratanya.
            'healthy' => collect($checks)->every(fn (array $check) => $check['ok']),
            'checks' => $checks,
            'last_inbound_at' => $this->lastMessageAt('in'),
            'last_reply_at' => $this->lastMessageAt('out'),
        ];
    }

    /**
     * @return array{key: string, ok: bool, hint: ?string}
     */
    private function chatbotActive(): array
    {
        return $this->check(
            'chatbot_active',
            (bool) ChatbotSetting::query()->value('is_active'),
        );
    }

    private function aiConfigured(): array
    {
        return $this->check('ai_configured', filled(config('services.deepseek.api_key')));
    }

    private function gatewayConfigured(): array
    {
        $server = WahaSetting::query()->first();
        $session = WhatsappSetting::query()->first();

        return $this->check(
            'gateway_configured',
            $server !== null && $server->isConfigured()
                && $session !== null && $session->isConfigured(),
        );
    }

    /**
     * Sesi WhatsApp benar-benar tersambung.
     *
     * Menanyakannya berarti menembak gateway, jadi kegagalan jaringan di sini
     * dilaporkan sebagai "tidak tersambung" — bukan dibiarkan melempar dan
     * menjatuhkan seluruh halaman diagnosa.
     */
    private function sessionConnected(): array
    {
        $client = app(WahaClient::class);

        if (! $client instanceof WahaClient) {
            return $this->check('session_connected', false);
        }

        try {
            return $this->check('session_connected', $client->isConnected());
        } catch (Throwable $e) {
            report($e);

            return $this->check('session_connected', false);
        }
    }

    private function webhookConfigured(): array
    {
        return $this->check('webhook_configured', filled(config('waha.webhook_token')));
    }

    /**
     * @return array{key: string, ok: bool, hint: ?string}
     */
    private function check(string $key, bool $ok): array
    {
        return [
            'key' => $key,
            'ok' => $ok,
            // Petunjuknya hanya ikut saat memang ada yang perlu dikerjakan.
            'hint' => $ok ? null : __('chatbot.diagnostics.'.$key),
        ];
    }

    private function lastMessageAt(string $direction): ?string
    {
        $at = ChatMessage::query()
            ->where('direction', $direction)
            ->latest('id')
            ->value('created_at');

        return $at === null ? null : Carbon::parse($at)->toIso8601String();
    }
}
