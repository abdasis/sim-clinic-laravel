<?php

namespace App\Actions\Broadcast;

use App\Actions\LogAuditAction;
use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use Illuminate\Support\Facades\Log;

/**
 * Hentikan blast yang gatewaynya sedang tidak bisa mengirim, dan biarkan
 * sisa penerimanya menunggu.
 *
 * Sesi WhatsApp yang putus di tengah jalan bukan salah nomor mana pun:
 * seluruh sisa antrean akan ditolak dengan cara yang sama. Menandai mereka
 * gagal satu per satu — 124 dari 130 pada isu #320 — menghapus jejak siapa
 * yang belum dikabari dan memaksa admin menebak dari mana harus mengulang.
 * Dijeda, sisanya tetap menunggu dan tinggal dilanjutkan setelah QR-nya
 * dipindai ulang.
 *
 * Job lain yang sudah terlanjur antre ikut berhenti sendiri: masing-masing
 * memeriksa status campaign sebelum mengirim.
 */
class PauseStalledBroadcastAction
{
    public function handle(Broadcast $broadcast, string $reason): void
    {
        // Job pertama yang sampai di sini yang menjeda; sisanya menyusul
        // dengan alasan yang sama dan tidak perlu mencatat ulang.
        if ($broadcast->status !== BroadcastStatus::Sending) {
            return;
        }

        $waiting = $broadcast->recipients()
            ->where('status', BroadcastRecipientStatus::Pending)
            ->count();

        $broadcast->update([
            'status' => BroadcastStatus::Paused,
            'paused_reason' => mb_substr($reason, 0, 250),
        ]);

        Log::error('Broadcast dijeda karena gateway WhatsApp tidak bisa mengirim.', [
            'broadcast_id' => $broadcast->id,
            'tenant_id' => $broadcast->tenant_id,
            'menunggu' => $waiting,
            'alasan' => $reason,
        ]);

        app(LogAuditAction::class)->handle(
            'broadcast.paused_by_gateway',
            $broadcast,
            null,
            [
                'old' => ['status' => BroadcastStatus::Sending],
                'new' => ['status' => BroadcastStatus::Paused, 'waiting' => $waiting, 'reason' => $reason],
            ],
            'Menjeda broadcast '.$broadcast->title.' karena WhatsApp klinik tidak bisa mengirim ('
                .$reason.'). '.$waiting.' penerima masih menunggu dan bisa dilanjutkan setelah sambungannya pulih.',
        );
    }
}
