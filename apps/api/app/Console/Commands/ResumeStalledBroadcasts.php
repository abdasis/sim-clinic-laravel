<?php

namespace App\Console\Commands;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\Tenant;
use App\Services\BroadcastService;
use App\Support\WahaClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lanjutkan sendiri broadcast yang berhenti karena gatewaynya tersendat.
 *
 * Halaman WhatsApp Web milik gateway kerap dimuat ulang di tengah blast
 * ratusan pesan — gejalanya "Execution context was destroyed" — lalu pulih
 * sendiri semenit kemudian. Tanpa ini, tiap tersendat berarti seseorang
 * harus menunggui layar dan menekan Lanjutkan; blast 130 pasien jadi
 * pekerjaan menjaga, bukan pekerjaan mengirim.
 *
 * Yang dijeda orang tidak ikut dilanjutkan: itu keputusan yang harus
 * dihormati, dan bedanya terbaca dari `paused_reason` yang kosong.
 */
class ResumeStalledBroadcasts extends Command
{
    protected $signature = 'clinic:resume-broadcasts';

    protected $description = 'Lanjutkan broadcast yang dijeda gateway setelah WhatsApp kliniknya tersambung lagi';

    /**
     * Batas percobaan otomatis per campaign.
     *
     * Gateway yang putus-nyambung terus tidak akan membaik karena digedor:
     * setelah sekian kali, campaign-nya dibiarkan menunggu orang.
     */
    private const MAX_AUTO_RESUMES = 5;

    public function handle(BroadcastService $broadcasts): int
    {
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use ($broadcasts): void {
            app()->instance('tenant', $tenant);

            try {
                $this->resumeFor($tenant, $broadcasts);
            } catch (Throwable $e) {
                Log::error('Gagal melanjutkan broadcast yang tertunda.', [
                    'exception' => $e,
                    'tenant' => $tenant->slug,
                ]);
            }
        });

        // Lepaskan binding terakhir agar tidak bocor ke proses berikutnya.
        app()->forgetInstance('tenant');

        return self::SUCCESS;
    }

    private function resumeFor(Tenant $tenant, BroadcastService $broadcasts): void
    {
        $stalled = Broadcast::query()
            ->where('status', BroadcastStatus::Paused)
            ->whereNotNull('paused_reason')
            ->where('auto_resumes', '<', self::MAX_AUTO_RESUMES)
            ->get();

        if ($stalled->isEmpty()) {
            return;
        }

        $client = app(WahaClient::class);

        // Gateway belum disetel, atau sesinya masih belum siap. Dibiarkan
        // menunggu putaran berikutnya — menekan kirim sekarang cuma
        // mengulang kegagalan yang sama.
        if ($client === null || ! $client->isConnected()) {
            return;
        }

        foreach ($stalled as $broadcast) {
            $broadcasts->queueSend($broadcast, automatic: true);

            $this->info("{$tenant->slug}: broadcast {$broadcast->title} dilanjutkan.");

            Log::info('Broadcast dilanjutkan otomatis setelah gateway pulih.', [
                'broadcast_id' => $broadcast->id,
                'tenant' => $tenant->slug,
                'percobaan_ke' => $broadcast->auto_resumes + 1,
            ]);
        }
    }
}
