<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Klien AI untuk chatbot, bicara lewat API bergaya OpenAI.
 *
 * Alamat, kunci, dan nama modelnya diserahkan saat pembuatan — sama seperti
 * WahaClient — jadi kelas ini tidak tahu-menahu soal config maupun tenant, dan
 * bisa diarahkan ke penyedia lain tanpa disentuh.
 *
 * Sengaja tanpa SDK: yang dibutuhkan hanya satu POST JSON, dan menambah
 * dependensi untuk itu tidak sepadan.
 */
class DeepSeekClient
{
    /** Balasan yang lebih lama dari ini praktis sudah tidak ditunggu pasien. */
    private const TIMEOUT_SECONDS = 60;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    /**
     * Satu putaran percakapan.
     *
     * Yang dikembalikan adalah isi `choices[0]` apa adanya — pemanggil yang
     * memutuskan apakah `tool_calls` perlu dijalankan dan diputar lagi.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array{message: array<string, mixed>, finish_reason: ?string}
     */
    public function chatCompletion(array $messages, array $tools = []): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            // Chatbot klinik menjawab dari data, bukan mengarang: suhu rendah
            // supaya jawabannya tidak berbunga-bunga dan mudah diulang.
            'temperature' => 0.2,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', $payload);
        } catch (Throwable $e) {
            Log::error('Gagal menghubungi penyedia AI chatbot.', [
                'exception' => $e,
                'model' => $this->model,
                'messages' => count($messages),
            ]);

            throw $e;
        }

        if (! $response->successful()) {
            Log::error('Penyedia AI chatbot menolak permintaan.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'model' => $this->model,
            ]);

            throw new RuntimeException('Penyedia AI menolak permintaan: HTTP '.$response->status());
        }

        $choice = $response->json('choices.0');

        if (! is_array($choice) || ! is_array($choice['message'] ?? null)) {
            Log::error('Balasan penyedia AI chatbot tidak dikenali.', [
                'body' => $response->body(),
                'model' => $this->model,
            ]);

            throw new RuntimeException('Balasan penyedia AI tidak dikenali.');
        }

        return [
            'message' => $choice['message'],
            'finish_reason' => is_string($choice['finish_reason'] ?? null) ? $choice['finish_reason'] : null,
        ];
    }
}
