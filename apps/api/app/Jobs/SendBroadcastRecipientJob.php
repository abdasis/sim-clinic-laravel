<?php

namespace App\Jobs;

use App\Actions\Broadcast\PauseStalledBroadcastAction;
use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Tenant;
use App\Support\WahaClient;
use App\Support\WahaException;
use App\Support\WahaSessionState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Kirim satu penerima broadcast dari antrian. Satu pesan satu job:
 * kegagalan satu nomor tidak menyentuh nomor lain, retry per nomor, dan
 * jarak antar job (diatur lewat delay saat dispatch) menjaga pengiriman
 * tidak menyembur ratusan pesan sekaligus.
 */
class SendBroadcastRecipientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * Detik tunggu antar percobaan.
     *
     * Panjang karena yang ditunggu biasanya halaman WhatsApp Web milik
     * gateway yang sedang dimuat ulang; itu butuh puluhan detik, bukan
     * sekejap. Nomor yang memang ditolak tidak ikut menunggu selama ini —
     * penolakan seperti itu tidak diulang sama sekali.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120, 300];

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
        if (! in_array($broadcast->status, [BroadcastStatus::Sending], true)) {
            return;
        }

        $client = app(WahaClient::class);

        if ($client === null) {
            $recipient->update([
                'status' => BroadcastRecipientStatus::Failed,
                'error' => __('broadcast.waha_not_ready'),
            ]);

            return;
        }

        $recipient->increment('attempts');

        try {
            $this->deliver($client, $broadcast, $recipient);
        } catch (Throwable $e) {
            Log::error('Gagal mengirim pesan broadcast dari antrian', [
                'exception' => $e,
                'broadcast_id' => $broadcast->id,
                'recipient_id' => $recipient->id,
                'attempt' => $recipient->attempts,
            ]);

            $state = $this->sessionState($client);

            // Sesi sehat tapi kirimannya ditolak mentah: yang bermasalah
            // nomor ini, bukan gatewaynya. Tidak diulang — penolakan begini
            // menjawab sama persis tiap kali — dan nomor lain jalan terus.
            if ($state === WahaSessionState::Working && $this->isNumberRejection($e)) {
                $recipient->update([
                    'status' => BroadcastRecipientStatus::Failed,
                    'error' => mb_substr($e->getMessage(), 0, 250),
                ]);
                $this->finishIfDrained($broadcast);

                return;
            }

            // Sesi yang butuh orang (QR perlu dipindai ulang, sesi mati)
            // tidak akan pulih sendiri; sisa antrean akan ditolak dengan cara
            // yang sama persis.
            if ($state->needsAttention()) {
                app(PauseStalledBroadcastAction::class)->handle($broadcast, $e->getMessage());

                return;
            }

            // Sisanya gangguan sesaat — halaman WhatsApp Web milik gateway
            // yang dimuat ulang di tengah blast, jaringan yang tersendat.
            // Yang begini pulih sendiri, jadi ditunggu dan diulang.
            if ($this->attempts() < $this->tries) {
                throw $e;
            }

            // Sudah ditunggu sampai habis dan gatewaynya masih tersendat.
            // Menandai nomor ini gagal menyalahkan orang yang salah, jadi
            // campaign-nya yang dijeda — sisanya tetap utuh menunggu.
            app(PauseStalledBroadcastAction::class)->handle($broadcast, $e->getMessage());

            return;
        }

        $recipient->update([
            'status' => BroadcastRecipientStatus::Sent,
            'sent_at' => now(),
            'error' => null,
        ]);

        $this->finishIfDrained($broadcast);
    }

    /**
     * Kirim pesannya, berikut gambar bila broadcast ini membawanya.
     *
     * Pesan jadi caption, bukan kiriman terpisah: dua kiriman berurutan
     * sampai sebagai dua notifikasi, dan yang kedua kerap terbaca lebih dulu
     * tanpa konteks gambarnya.
     */
    private function deliver(WahaClient $client, Broadcast $broadcast, BroadcastRecipient $recipient): void
    {
        $image = $broadcast->image_path;

        if ($image === null) {
            $client->send($recipient->phone, $recipient->message);

            return;
        }

        $disk = Storage::disk('public');

        // Berkasnya bisa hilang belakangan (dibersihkan, disk diganti).
        // Pesannya tetap berangkat sebagai teks — kehilangan poster lebih
        // ringan daripada pasien tidak menerima kabar sama sekali.
        if (! $disk->exists($image)) {
            Log::warning('Gambar broadcast tidak ditemukan; pesan dikirim sebagai teks.', [
                'broadcast_id' => $broadcast->id,
                'image_path' => $image,
            ]);

            $client->send($recipient->phone, $recipient->message);

            return;
        }

        $client->sendImage(
            $recipient->phone,
            $disk->get($image),
            $disk->mimeType($image) ?: 'image/jpeg',
            basename($image),
            $recipient->message,
        );
    }

    /**
     * Keadaan sesi saat kiriman ditolak.
     *
     * Ditanyakan ke gateway, bukan ditebak dari isi balasannya: WAHA memakai
     * 422 yang sama untuk sesi terputus dan nomor yang ditolak, jadi
     * mencocokkan kalimat errornya cuma memindahkan tebakan. Keadaan sesinya
     * yang memisahkan keduanya.
     *
     * Gateway yang tidak bisa ditanya dihitung "belum jelas", bukan mati:
     * dari sisi aplikasi itu tidak terbedakan dari tersendat sesaat, dan
     * menunggu sebentar jauh lebih murah daripada salah menghentikan blast.
     */
    private function sessionState(WahaClient $client): WahaSessionState
    {
        try {
            return $client->sessionState();
        } catch (Throwable $e) {
            Log::error('Gagal memeriksa sesi WhatsApp setelah pengiriman ditolak.', [
                'exception' => $e,
                'recipient_id' => $this->recipientId,
            ]);

            return WahaSessionState::Unknown;
        }
    }

    /**
     * Penolakan yang menyangkut nomor atau isi kirimannya sendiri, bukan
     * kesehatan gateway.
     *
     * 4xx berarti gateway paham permintaannya dan tetap menolak — nomor tidak
     * terdaftar di WhatsApp, misalnya. 5xx sebaliknya: gatewaynya yang
     * tersandung, dan itu bukan urusan nomor yang kebetulan sedang antre.
     */
    private function isNumberRejection(Throwable $e): bool
    {
        return $e instanceof WahaException && $e->status >= 400 && $e->status < 500;
    }

    /**
     * Dipanggil saat job mati di luar handle(): percobaan habis, worker
     * dihentikan, atau timeout.
     *
     * Tanpa ini penerimanya tertinggal "menunggu" selamanya dan campaign-nya
     * tidak pernah menutup — dua penerima di isu #320 tersangkut begitu,
     * dan statusnya berhenti di "sedang mengirim" walau workernya sudah lama
     * berhenti.
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

        $recipient->update([
            'status' => BroadcastRecipientStatus::Failed,
            'error' => mb_substr($e?->getMessage() ?: __('broadcast.job_died'), 0, 250),
        ]);

        $this->finishIfDrained($recipient->broadcast);
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
