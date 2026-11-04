<?php

namespace App\Jobs;

use App\Actions\Broadcast\PauseStalledBroadcastAction;
use App\Actions\Inbox\RecordOutboundMessageAction;
use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Enums\ConversationChannel;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Tenant;
use App\Models\WhatsappSetting;
use App\Support\DailyMessageQuota;
use App\Support\WhatsappGatewayClient;
use App\Support\WhatsappGatewayException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kirim satu penerima broadcast dari antrian. Satu pesan satu job:
 * kegagalan satu nomor tidak menyentuh nomor lain, retry per nomor, dan
 * jarak antar job (diatur lewat delay saat dispatch) menjaga pengiriman
 * tidak menyembur ratusan pesan sekaligus.
 *
 * Jalurnya gateway WhatsApp internal, jadi teks bebas boleh dikirim tanpa
 * template yang disetujui Meta lebih dulu. Pesan tiap penerima sudah
 * dirender saat kampanye dibuat; job tinggal mengirim kolom `message`.
 */
class SendBroadcastRecipientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * Detik tunggu antar percobaan — untuk gangguan sesaat di sisi gateway.
     *
     * Nomor yang memang ditolak tidak ikut menunggu selama ini; penolakan
     * seperti itu tidak diulang sama sekali.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120, 300];

    /** Detik diam bawaan bila gateway menahan laju tanpa menyebut angkanya. */
    private const DEFAULT_THROTTLE_SECONDS = 60;

    /**
     * Kode yang berarti sesi WhatsApp klinik yang hilang, bukan nomor yang
     * salah. Seluruh sisa antrean akan ditolak dengan cara yang sama.
     */
    private const SESSION_GONE_CODES = [
        'SESSION_NOT_CONNECTED',
        'SESSION_NOT_FOUND',
        'SESSION_NOT_CONFIGURED',
    ];

    /**
     * Kode yang benar-benar menolak nomor penerimanya. Hanya ini yang layak
     * menghanguskan satu penerima; sisanya bukan salah nomor.
     */
    private const RECIPIENT_REJECTED_CODES = [
        'INVALID_PHONE_NUMBER',
    ];

    public function __construct(
        public readonly int $recipientId,
        public readonly int $tenantId,
    ) {}

    public function handle(): void
    {
        // Job berjalan tanpa middleware ResolveTenant, jadi tenant diikat
        // manual supaya global scope dan setelan tenant bekerja normal.
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        app()->instance('tenant', $tenant);

        $recipient = BroadcastRecipient::query()->find($this->recipientId);

        if ($recipient === null || $recipient->status !== BroadcastRecipientStatus::Pending) {
            return;
        }

        $broadcast = $recipient->broadcast;

        // Jeda/batal dihormati di sini, bukan hanya di UI: job yang sudah
        // terlanjur antre tidak mengirim apa pun.
        if ($broadcast->status !== BroadcastStatus::Sending) {
            return;
        }

        $setting = WhatsappSetting::query()->first();

        // Tanpa kredensial gateway tidak ada yang bisa dikirim, dan
        // mengulanginya tidak akan berubah. Penerimanya ditandai gagal dengan
        // sebab yang bisa dibaca admin.
        if ($setting === null || ! $setting->provisioned()) {
            $recipient->update([
                'status' => BroadcastRecipientStatus::Failed,
                'error' => __('broadcast.gateway_not_ready'),
            ]);
            $this->finishIfDrained($broadcast);

            return;
        }

        // Kuota diperiksa di sini, bukan saat mengantre: job yang diantrekan
        // pagi bisa berjalan sore setelah campaign lain ikut menghabiskan
        // jatah nomor yang sama. Yang menentukan keadaan saat pesannya
        // benar-benar berangkat.
        $quota = DailyMessageQuota::today();

        if ($quota->isSpent()) {
            app(PauseStalledBroadcastAction::class)->handle(
                $broadcast,
                __('broadcast.daily_limit_reached', ['limit' => $quota->limit]),
                $this->minutesUntilTomorrow(),
            );

            return;
        }

        $recipient->increment('attempts');

        try {
            $providerMessageId = $this->deliver($setting, $broadcast, $recipient);
        } catch (Throwable $e) {
            Log::error('Gagal mengirim pesan broadcast dari antrian', [
                'exception' => $e,
                'broadcast_id' => $broadcast->id,
                'recipient_id' => $recipient->id,
                'attempt' => $recipient->attempts,
            ]);

            // Sesi putus bukan salah nomor mana pun: seluruh sisa antrean akan
            // ditolak dengan cara yang sama. Campaign dijeda agar sisanya tetap
            // menunggu dan bisa dilanjutkan setelah WhatsApp tersambung lagi.
            if ($e instanceof WhatsappGatewayException && $this->sessionIsGone($e)) {
                $this->pause($broadcast, $this->pauseReason($e));

                return;
            }

            // Ditahan laju: pesan ini belum tentu ditolak, hanya harus
            // didiamkan dulu. Dikembalikan ke antrean tanpa menghanguskan
            // penerimanya; kunci idempotensi yang sama mencegah kiriman ganda
            // bila ternyata pesan sebelumnya sempat berangkat. Bila percobaan
            // benar-benar habis, failed() yang menjeda campaign-nya.
            if ($e instanceof WhatsappGatewayException && $this->isThrottled($e)) {
                if ($this->job !== null) {
                    $this->release($e->retryAfter ?? self::DEFAULT_THROTTLE_SECONDS);
                }

                return;
            }

            // Hanya penolakan nomor yang menjawab sama persis tiap kali.
            // Nomor itu ditandai gagal dan nomor lain jalan terus.
            if ($e instanceof WhatsappGatewayException && $this->recipientIsRejected($e)) {
                $recipient->update([
                    'status' => BroadcastRecipientStatus::Failed,
                    'error' => mb_substr($e->getMessage(), 0, 250),
                ]);
                $this->finishIfDrained($broadcast);

                return;
            }

            // Jaringan putus, 5xx, 401/403, atau galat yang belum dikenal:
            // gateway belum sempat menilai nomornya. Menandai penerima gagal
            // di sini menghabiskan seluruh antrean karena satu gangguan yang
            // sama — campaign dijeda dan sisanya tetap menunggu.
            $this->pause($broadcast, $this->pauseReason($e));

            return;
        }

        $recipient->update([
            'status' => BroadcastRecipientStatus::Sent,
            'sent_at' => now(),
            'error' => null,
        ]);

        $this->recordOutbound($recipient, $providerMessageId);

        $this->finishIfDrained($broadcast);
    }

    /**
     * Catat pesan ke kotak masuk setelah benar-benar terkirim.
     *
     * Kegagalan mencatat tidak boleh mengubah penerima yang sudah terkirim
     * menjadi gagal — pesannya sudah sampai ke pasien, dan mengirim ulang
     * hanya akan membuat pasien menerimanya dua kali.
     */
    private function recordOutbound(BroadcastRecipient $recipient, ?string $providerMessageId): void
    {
        try {
            app(RecordOutboundMessageAction::class)->handle(
                $this->tenantId,
                $recipient->phone,
                $recipient->message,
                ConversationChannel::Gateway,
                $providerMessageId,
            );
        } catch (Throwable $e) {
            Log::error('Pesan broadcast terkirim tetapi gagal dicatat ke kotak masuk.', [
                'exception' => $e,
                'broadcast_id' => $recipient->broadcast_id,
                'recipient_id' => $recipient->id,
            ]);
        }
    }

    /**
     * Kirim pesannya, berikut poster sebagai lampiran gambar bila ada.
     *
     * Kunci idempotensi memakai nomor percobaan kirim, bukan percobaan
     * antrean: pengiriman ulang oleh worker untuk pesan yang sama tetap
     * dibuang gateway, sedangkan kampanye yang diantrekan ulang lewat
     * "Kirim" memang pesan baru dan harus berangkat.
     */
    private function deliver(
        WhatsappSetting $setting,
        Broadcast $broadcast,
        BroadcastRecipient $recipient,
    ): ?string {
        $client = app(WhatsappGatewayClient::class);
        $key = 'broadcast-'.$recipient->id.'-'.$recipient->send_attempt;

        if ($broadcast->image_url !== null) {
            return $client->sendImage(
                $setting,
                $recipient->phone,
                $broadcast->image_url,
                $recipient->message,
                $key,
            );
        }

        return $client->sendText($setting, $recipient->phone, $recipient->message, $key);
    }

    /** Sesi WhatsApp klinik tidak tersedia di gateway. */
    private function sessionIsGone(WhatsappGatewayException $e): bool
    {
        return in_array($e->code, self::SESSION_GONE_CODES, true);
    }

    /** Gateway menahan laju kiriman (HTTP 429). */
    private function isThrottled(WhatsappGatewayException $e): bool
    {
        return $e->status === 429 || $e->code === 'RATE_LIMIT_EXCEEDED';
    }

    /** Nomor penerima memang ditolak gateway, bukan sekadar gangguannya. */
    private function recipientIsRejected(WhatsappGatewayException $e): bool
    {
        return in_array($e->code, self::RECIPIENT_REJECTED_CODES, true);
    }

    /**
     * Alasan jeda yang bisa dibaca admin. Pesan gateway dipakai apa adanya;
     * galat tanpa pesan diberi kalimat pengganti, bukan teks kosong.
     */
    private function pauseReason(Throwable $e): string
    {
        $message = trim($e->getMessage());

        return $message !== '' ? $message : __('broadcast.job_died');
    }

    /** Jeda campaign lewat satu pintu agar alasannya selalu tercatat. */
    private function pause(Broadcast $broadcast, string $reason): void
    {
        app(PauseStalledBroadcastAction::class)->handle($broadcast, $reason);
    }

    /**
     * Sisa menit sampai hari berganti — saat jatah kirimnya penuh lagi.
     *
     * Lewat sedikit dari tengah malam, bukan tepat: campaign yang dilanjutkan
     * persis saat pergantian hari beresiko masih terhitung di hari kemarin
     * oleh kueri yang sepersekian detik lebih dulu.
     */
    private function minutesUntilTomorrow(): int
    {
        return (int) ceil(now()->diffInMinutes(now()->addDay()->startOfDay())) + 5;
    }

    /**
     * Dipanggil saat job mati di luar handle(): percobaan habis, worker
     * dihentikan, atau timeout.
     *
     * Percobaan yang habis bukan bukti nomornya ditolak: bisa jadi gateway
     * yang berulang kali tidak menjawab. Penerimanya dibiarkan menunggu dan
     * campaign-nya dijeda, supaya bisa dilanjutkan setelah sambungannya
     * pulih. Menandainya gagal justru menghapus jejak siapa yang belum
     * dikabari — persis yang terjadi pada isu #320.
     */
    public function failed(?Throwable $e): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        app()->instance('tenant', $tenant);

        $recipient = BroadcastRecipient::query()->find($this->recipientId);

        if ($recipient === null || $recipient->status !== BroadcastRecipientStatus::Pending) {
            return;
        }

        Log::error('Percobaan mengirim broadcast habis; campaign dijeda.', [
            'exception' => $e,
            'broadcast_id' => $recipient->broadcast_id,
            'recipient_id' => $recipient->id,
            'attempts' => $recipient->attempts,
        ]);

        $this->pause(
            $recipient->broadcast,
            $e === null ? __('broadcast.job_died') : $this->pauseReason($e),
        );
    }

    /**
     * Tutup campaign begitu tidak ada lagi penerima menunggu.
     *
     * Antrean yang habis belum tentu berarti pesannya sampai: kalau tidak
     * satu pun berangkat, campaign ditutup sebagai gagal. Sebelumnya semua
     * keadaan berakhir "Selesai", sehingga blast yang seluruhnya ditolak
     * gateway terbaca persis sama dengan blast yang berhasil.
     */
    private function finishIfDrained(Broadcast $broadcast): void
    {
        $stillPending = $broadcast->recipients()
            ->where('status', BroadcastRecipientStatus::Pending)
            ->exists();

        if ($stillPending || $broadcast->status !== BroadcastStatus::Sending) {
            return;
        }

        $anySent = $broadcast->recipients()
            ->where('status', BroadcastRecipientStatus::Sent)
            ->exists();

        $broadcast->update([
            'status' => $anySent ? BroadcastStatus::Done : BroadcastStatus::Failed,
        ]);
    }
}
