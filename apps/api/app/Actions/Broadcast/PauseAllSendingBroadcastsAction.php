<?php

namespace App\Actions\Broadcast;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;

/**
 * Hentikan semua blast yang sedang jalan untuk satu klinik sekaligus.
 *
 * Jatah harian milik nomornya, bukan milik satu campaign. Begitu batasnya
 * tersentuh, campaign lain yang antreannya masih jalan akan ditolak dengan
 * alasan yang sama — menjeda satu per satu berarti membiarkan sisanya
 * menabrak gateway dan menandai penerimanya gagal. Satu pintu jeda dipakai
 * supaya alasan, cooldown, dan catatan auditnya seragam.
 */
class PauseAllSendingBroadcastsAction
{
    /**
     * @param  int  $tenantId  klinik pemilik nomor pengirim
     * @param  int|null  $coolDownMinutes  masa diam sebelum boleh dicoba lagi
     * @return int jumlah campaign yang dijeda
     */
    public function handle(int $tenantId, string $reason, ?int $coolDownMinutes = null): int
    {
        $broadcasts = Broadcast::query()
            ->where('tenant_id', $tenantId)
            ->where('status', BroadcastStatus::Sending)
            ->get();

        foreach ($broadcasts as $broadcast) {
            app(PauseStalledBroadcastAction::class)->handle($broadcast, $reason, $coolDownMinutes);
        }

        return $broadcasts->count();
    }
}
