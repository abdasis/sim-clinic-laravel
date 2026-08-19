<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\ChatbotService;
use App\Support\WahaClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Jawab satu pesan masuk.
 *
 * Dikerjakan di antrian karena menunggu AI bisa memakan puluhan detik,
 * sementara gateway WhatsApp menganggap webhook yang lambat sebagai gagal dan
 * mengirim ulang pesannya.
 *
 * Sekali coba saja. Percakapan itu punya waktu: balasan yang baru sampai lima
 * menit kemudian karena percobaan ulang lebih membingungkan pasien daripada
 * tidak dibalas sama sekali, dan pesan yang sudah terlanjur terkirim tidak bisa
 * ditarik saat percobaan kedua ikut jalan.
 */
class ProcessInboundMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /** Batas pesan per nomor per menit — pagar biaya, bukan pagar keamanan. */
    private const RATE_LIMIT = 10;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $senderPhone,
        public readonly string $body,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        // Job tidak lewat middleware ResolveTenant, jadi tenant diikat manual
        // supaya TenantScope aktif sebelum kueri apa pun berjalan.
        app()->instance('tenant', $tenant);

        if (! $this->withinRateLimit()) {
            return;
        }

        try {
            $answer = app(ChatbotService::class)->reply($this->senderPhone, $this->body);
        } catch (Throwable $e) {
            Log::error('Chatbot gagal menyusun balasan.', [
                'exception' => $e,
                'tenant_id' => $this->tenantId,
                'sender_phone' => $this->senderPhone,
            ]);

            // Diamnya sistem terbaca pasien sebagai diabaikan; satu kalimat
            // jujur lebih baik daripada tidak ada balasan sama sekali.
            $answer = __('chatbot.fallback');
        }

        if ($answer === null) {
            return;
        }

        $this->send($answer);
    }

    private function withinRateLimit(): bool
    {
        $key = 'chatbot:'.$this->tenantId.'|'.$this->senderPhone;

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT)) {
            Log::warning('Pesan chatbot dilewati karena melewati batas laju.', [
                'tenant_id' => $this->tenantId,
                'sender_phone' => $this->senderPhone,
            ]);

            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    private function send(string $answer): void
    {
        $client = app(WahaClient::class);

        if (! $client instanceof WahaClient) {
            Log::error('Balasan chatbot tidak terkirim: gateway WhatsApp belum siap.', [
                'tenant_id' => $this->tenantId,
                'sender_phone' => $this->senderPhone,
            ]);

            return;
        }

        try {
            $client->send($this->senderPhone, $answer);
        } catch (Throwable $e) {
            Log::error('Balasan chatbot gagal dikirim lewat gateway WhatsApp.', [
                'exception' => $e,
                'tenant_id' => $this->tenantId,
                'sender_phone' => $this->senderPhone,
            ]);
        }
    }
}
