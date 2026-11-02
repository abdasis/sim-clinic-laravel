<?php

namespace App\Jobs;

use App\Actions\Inbox\RecordOutboundMessageAction;
use App\Enums\ConversationChannel;
use App\Models\Tenant;
use App\Services\ChatbotService;
use App\Support\CloudApiClient;
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
 *
 * Jalur balasnya mengikuti kanal pesan masuk: pesan Cloud API Meta hanya bisa
 * dibalas lewat Cloud API, pesan WAHA lewat sesi WAHA. Menyalurkan semuanya ke
 * satu gateway membuat balasan untuk kanal lain hilang tanpa jejak di layar
 * pasien.
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
        public readonly ConversationChannel $channel = ConversationChannel::Waha,
    ) {}

    public function handle(): void
    {
        Log::channel('chatbot')->info('Job diproses', [
            'tenant_id' => $this->tenantId,
            'from' => $this->senderPhone,
            'body' => $this->body,
        ]);

        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            Log::channel('chatbot')->error('Job: tenant tidak ditemukan', ['tenant_id' => $this->tenantId]);

            return;
        }

        // Job tidak lewat middleware ResolveTenant, jadi tenant diikat manual
        // supaya TenantScope aktif sebelum kueri apa pun berjalan.
        app()->instance('tenant', $tenant);

        if (! $this->withinRateLimit()) {
            Log::channel('chatbot')->warning('Job: melewati batas rate limit', [
                'tenant_id' => $this->tenantId,
                'from' => $this->senderPhone,
            ]);

            return;
        }

        // Pendekatan B: indikator dinyalakan sebelum AI bekerja, bukan sekejap
        // sebelum pesan terkirim. Jeda yang perlu ditemani justru jeda
        // penyusunan balasan — itulah detik-detik saat pasien menatap layar
        // diam. Konsekuensinya stopTyping wajib jalan di semua jalur keluar,
        // termasuk saat balasannya null, jadi seluruh sisa proses dibungkus
        // finally.
        // Chatbot yang mati harus benar-benar diam: memunculkan "sedang
        // mengetik" untuk balasan yang tidak akan pernah datang justru
        // menjanjikan sesuatu yang tidak ditepati.
        $gateway = app(ChatbotService::class)->isActive() ? $this->gateway() : null;

        // Tidak ada gateway berarti balasannya tidak akan pernah sampai ke
        // mana pun. Menyusunnya tetap berarti membayar penyedia AI untuk
        // kalimat yang langsung dibuang, dan itu terjadi pada setiap pesan
        // masuk selama gatewaynya putus — justru saat tidak ada seorang pun
        // yang menyadarinya.
        if ($gateway === null) {
            Log::channel('chatbot')->warning('Job: dihentikan sebelum menyusun balasan', [
                'tenant_id' => $this->tenantId,
                'from' => $this->senderPhone,
                'channel' => $this->channel->value,
                'alasan' => 'chatbot mati atau gateway WhatsApp belum siap',
            ]);

            return;
        }

        // Indikator mengetik hanya ada di WAHA. Cloud API Meta tidak punya
        // endpoint untuk itu, jadi jalur Cloud API langsung menyusun balasan.
        $showsTyping = $gateway instanceof WahaClient;

        if ($showsTyping) {
            $gateway->startTyping($this->senderPhone);
        }

        try {
            $this->respond($gateway);
        } finally {
            if ($showsTyping) {
                $gateway->stopTyping($this->senderPhone);
            }
        }
    }

    /** Susun balasan lalu kirim; dipanggil di antara start/stop typing. */
    private function respond(WahaClient|CloudApiClient $gateway): void
    {
        try {
            $answer = app(ChatbotService::class)->reply($this->senderPhone, $this->body);
        } catch (Throwable $e) {
            Log::error('Chatbot gagal menyusun balasan.', [
                'exception' => $e,
                'tenant_id' => $this->tenantId,
                'sender_phone' => $this->senderPhone,
            ]);
            Log::channel('chatbot')->error('Job: chatbot gagal menyusun balasan', [
                'exception' => $e->getMessage(),
                'tenant_id' => $this->tenantId,
                'from' => $this->senderPhone,
            ]);

            // Diamnya sistem terbaca pasien sebagai diabaikan; satu kalimat
            // jujur lebih baik daripada tidak ada balasan sama sekali.
            $answer = __('chatbot.fallback');
        }

        if ($answer === null) {
            Log::channel('chatbot')->warning('Job: balasan kosong (null), tidak dikirim', [
                'tenant_id' => $this->tenantId,
                'from' => $this->senderPhone,
            ]);

            return;
        }

        Log::channel('chatbot')->info('Job: balasan disiapkan, akan dikirim', [
            'tenant_id' => $this->tenantId,
            'from' => $this->senderPhone,
            'answer' => $answer,
        ]);

        $this->send($answer, $gateway);
    }

    /**
     * Gateway yang sesuai kanal pesan masuk, atau null bila belum disetel.
     * Diambil sekali supaya indikator mengetik dan pengiriman memakai sesi
     * yang sama.
     */
    private function gateway(): WahaClient|CloudApiClient|null
    {
        if ($this->channel === ConversationChannel::CloudApi) {
            $client = app(CloudApiClient::class);

            if ($client instanceof CloudApiClient) {
                return $client;
            }

            Log::error('Balasan chatbot tidak terkirim: Cloud API Meta belum siap.', [
                'tenant_id' => $this->tenantId,
                'sender_phone' => $this->senderPhone,
            ]);

            return null;
        }

        $client = app(WahaClient::class);

        if (! $client instanceof WahaClient) {
            Log::error('Balasan chatbot tidak terkirim: gateway WhatsApp belum siap.', [
                'tenant_id' => $this->tenantId,
                'sender_phone' => $this->senderPhone,
            ]);

            return null;
        }

        return $client;
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

    private function send(string $answer, WahaClient|CloudApiClient $gateway): void
    {
        $providerMessageId = null;

        try {
            if ($gateway instanceof CloudApiClient) {
                // Cloud API mengembalikan id pesan; disimpan supaya status
                // terkirim/terbaca dari webhook Meta bisa dicocokkan nanti.
                $providerMessageId = $gateway->sendText($this->senderPhone, $answer);
            } else {
                $gateway->send($this->senderPhone, $answer);
            }
        } catch (Throwable $e) {
            Log::error('Balasan chatbot gagal dikirim lewat gateway WhatsApp.', [
                'exception' => $e,
                'tenant_id' => $this->tenantId,
                'sender_phone' => $this->senderPhone,
                'channel' => $this->channel->value,
            ]);

            return;
        }

        $this->recordOutbound($answer, $providerMessageId);
    }

    /**
     * Balasan yang sudah sampai juga dicatat ke kotak masuk, supaya utasnya
     * tidak terbaca satu arah. Kegagalan mencatat tidak boleh membuat worker
     * mengulang pengiriman yang sudah berhasil.
     */
    private function recordOutbound(string $answer, ?string $providerMessageId = null): void
    {
        try {
            app(RecordOutboundMessageAction::class)->handle(
                $this->tenantId,
                $this->senderPhone,
                $answer,
                $this->channel,
                $providerMessageId,
            );
        } catch (Throwable $e) {
            Log::error('Balasan chatbot terkirim tetapi gagal dicatat ke kotak masuk.', [
                'exception' => $e,
                'tenant_id' => $this->tenantId,
                'sender_phone' => $this->senderPhone,
                'channel' => $this->channel->value,
            ]);
        }
    }
}
