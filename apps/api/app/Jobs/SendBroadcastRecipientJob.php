<?php

namespace App\Jobs;

use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Tenant;
use App\Support\WhatsappClientFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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

        $client = app(WhatsappClientFactory::class)->forCurrentTenant();

        if ($client === null) {
            $recipient->update([
                'status' => BroadcastRecipientStatus::Failed,
                'error' => __('broadcast.gateway_not_ready'),
            ]);

            return;
        }

        $recipient->increment('attempts');

        try {
            $client->send($recipient->phone, $recipient->message);
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim pesan broadcast dari antrian', [
                'exception' => $e,
                'broadcast_id' => $broadcast->id,
                'recipient_id' => $recipient->id,
                'attempt' => $recipient->attempts,
            ]);

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

    /** Tutup campaign begitu tidak ada lagi penerima menunggu. */
    private function finishIfDrained(Broadcast $broadcast): void
    {
        $stillPending = $broadcast->recipients()
            ->where('status', BroadcastRecipientStatus::Pending)
            ->exists();

        if (! $stillPending && $broadcast->status === BroadcastStatus::Sending) {
            $broadcast->update(['status' => BroadcastStatus::Done]);
        }
    }
}
