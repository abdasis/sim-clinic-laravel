<?php

namespace App\Console\Commands;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\Tenant;
use App\Services\BroadcastService;
use App\Support\AutoReminderEngine;
use App\Support\WahaClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dijalankan scheduler tiap pagi: susun follow-up otomatis untuk tiap klinik
 * yang mengaktifkannya. Bila WAHA siap, langsung diantrekan; bila belum,
 * broadcast-nya menunggu admin di menu Broadcast WA.
 *
 * Terpisah dari clinic:send-reminders yang bekerja per-layanan. Keduanya
 * dijadwalkan berjarak supaya beban pengirimannya tidak menumpuk.
 */
class SendAutoReminders extends Command
{
    protected $signature = 'clinic:send-auto-reminders';

    protected $description = 'Susun dan kirim follow-up otomatis berdasarkan rekam medis terakhir';

    public function handle(): int
    {
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant): void {
            app()->instance('tenant', $tenant);

            try {
                $result = (new AutoReminderEngine)->run();

                if ($result['created'] === 0) {
                    return;
                }

                $this->info("{$tenant->slug}: {$result['recipients']} pasien di-follow-up.");

                if (app(WahaClient::class) !== null) {
                    $this->queueTodaysAutoBroadcasts();
                }
            } catch (\Throwable $e) {
                Log::error('Gagal menyusun follow-up otomatis untuk tenant', [
                    'exception' => $e,
                    'tenant' => $tenant->slug,
                ]);
            }
        });

        // Lepaskan binding terakhir agar tidak bocor ke proses berikutnya.
        app()->forgetInstance('tenant');

        return self::SUCCESS;
    }

    /**
     * Hanya broadcast otomatis hari ini yang diantrekan. Tanpa saringan
     * `auto`, pengingat per-layanan yang masih menunggu kiriman manual ikut
     * terkirim oleh command ini.
     */
    private function queueTodaysAutoBroadcasts(): void
    {
        Broadcast::query()
            ->where('status', BroadcastStatus::Ready)
            ->where('audience_params->auto', true)
            ->whereDate('created_at', today())
            ->each(fn (Broadcast $broadcast) => app(BroadcastService::class)->queueSend($broadcast));
    }
}
