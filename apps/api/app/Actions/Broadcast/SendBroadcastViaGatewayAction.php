<?php

namespace App\Actions\Broadcast;

use App\Actions\LogAuditAction;
use App\Enums\BroadcastRecipientStatus;
use App\Models\Broadcast;
use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Kirim semua penerima pending lewat gateway HTTP (format Fonnte/Wablas:
 * POST form `target` + `message`, token di header Authorization).
 *
 * Tiap penerima berdiri sendiri: satu nomor gagal tidak menghentikan sisanya,
 * dan galatnya tersimpan di baris penerima supaya bisa dicoba ulang.
 *
 * ponytail: pengiriman sinkron, cukup sampai ratusan penerima per broadcast.
 * Bila tenant mulai mengirim ribuan, pindahkan loop ini ke queued job.
 */
class SendBroadcastViaGatewayAction
{
    /**
     * @return array{sent: int, failed: int}
     */
    public function handle(Broadcast $broadcast, WhatsappSetting $setting): array
    {
        $sent = 0;
        $failed = 0;

        $pending = $broadcast->recipients()
            ->where('status', BroadcastRecipientStatus::Pending)
            ->orderBy('id')
            ->get();

        foreach ($pending as $recipient) {
            try {
                $response = Http::timeout(20)
                    ->withHeaders(['Authorization' => $setting->api_token])
                    ->asForm()
                    ->post($setting->api_url, [
                        'target' => $recipient->phone,
                        'message' => $recipient->message,
                    ]);

                if ($response->successful()) {
                    $recipient->update([
                        'status' => BroadcastRecipientStatus::Sent,
                        'sent_at' => now(),
                        'error' => null,
                    ]);
                    $sent++;

                    continue;
                }

                $error = 'HTTP '.$response->status();
                $recipient->update(['status' => BroadcastRecipientStatus::Failed, 'error' => $error]);
                $failed++;

                Log::error('Gateway WhatsApp menolak pesan broadcast', [
                    'broadcast_id' => $broadcast->id,
                    'recipient_id' => $recipient->id,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);
            } catch (\Throwable $e) {
                $recipient->update([
                    'status' => BroadcastRecipientStatus::Failed,
                    'error' => mb_substr($e->getMessage(), 0, 250),
                ]);
                $failed++;

                Log::error('Gagal menghubungi gateway WhatsApp', [
                    'exception' => $e,
                    'broadcast_id' => $broadcast->id,
                    'recipient_id' => $recipient->id,
                ]);
            }
        }

        app(LogAuditAction::class)->handle(
            'broadcast.sent',
            $broadcast,
            Auth::user(),
            ['new' => ['sent' => $sent, 'failed' => $failed, 'attempted' => $pending->count()]],
            'Mengirim broadcast '.$broadcast->title.' lewat gateway: '
                .$sent.' terkirim, '.$failed.' gagal.',
        );

        return ['sent' => $sent, 'failed' => $failed];
    }
}
