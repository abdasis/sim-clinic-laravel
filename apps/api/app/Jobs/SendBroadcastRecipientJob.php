<?php

namespace App\Jobs;

use App\Actions\Broadcast\PauseStalledBroadcastAction;
use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Tenant;
use App\Support\WahaClient;
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

    public int $tries = 3;

    /** @var array<int, int> detik tunggu antar percobaan */
    public array $backoff = [30, 120];

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

            // Sesi yang putus akan menolak sisa antreannya dengan cara yang
            // sama persis. Diperiksa sebelum retry: mengulang 3 kali per
            // nomor terhadap sesi mati cuma menahan antrean berjam-jam,
            // lalu menghanguskan semua orang di ujungnya.
            if ($this->sessionIsDown($client)) {
                app(PauseStalledBroadcastAction::class)->handle($broadcast, $e->getMessage());

                return;
            }

            if ($this->attempts() >= $this->tries) {
                $recipient->update([
                    'status' => BroadcastRecipientStatus::Failed,
                    'error' => mb_substr($e->getMessage(), 0, 250),
                ]);
                $this->finishIfDrained($broadcast);

                return;
            }

            throw $e; // biarkan queue melakukan retry dengan backoff
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
     * Sesi WhatsApp-nya masih hidup?
     *
     * Ditanyakan ke gateway, bukan ditebak dari isi balasannya: WAHA memakai
     * 422 yang sama untuk sesi terputus, nomor yang ditolak, dan payload yang
     * cacat, jadi mencocokkan kalimat errornya cuma memindahkan tebakan.
     *
     * Gateway yang tidak bisa ditanya dianggap mati juga. Menjeda campaign
     * yang sebenarnya sehat masih bisa dilanjutkan sekali klik; menghanguskan
     * ratusan penerima yang sebenarnya baik-baik saja tidak.
     */
    private function sessionIsDown(WahaClient $client): bool
    {
        try {
            return ! $client->isConnected();
        } catch (Throwable $e) {
            Log::error('Gagal memeriksa sesi WhatsApp setelah pengiriman ditolak.', [
                'exception' => $e,
                'recipient_id' => $this->recipientId,
            ]);

            return true;
        }
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
