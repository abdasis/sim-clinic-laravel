<?php

namespace App\Console\Commands;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\Tenant;
use App\Services\BroadcastService;
use App\Support\ReminderEngine;
use App\Support\WahaClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dijalankan scheduler tiap pagi: susun broadcast pengingat perawatan untuk
 * tiap tenant. Bila pengiriman otomatis (gateway/QR) siap, langsung
 * diantrekan; bila manual, broadcast menunggu admin di menu Broadcast WA.
 */
class SendTreatmentReminders extends Command
{
    protected $signature = 'clinic:send-reminders';

    protected $description = 'Susun dan kirim pengingat perawatan ulang untuk semua tenant';

    public function handle(): int
    {
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant): void {
            app()->instance('tenant', $tenant);

            try {
                $result = (new ReminderEngine)->run();

                if ($result['created'] === 0) {
                    return;
                }

                $this->info("{$tenant->slug}: {$result['created']} pengingat, {$result['recipients']} penerima.");

                if (app(WahaClient::class) !== null) {
                    Broadcast::query()
                        ->where('kind', 'reminder')
                        ->where('status', BroadcastStatus::Ready)
                        ->whereDate('created_at', today())
                        ->each(fn ($broadcast) => app(BroadcastService::class)->queueSend($broadcast));
                }
            } catch (\Throwable $e) {
                Log::error('Gagal menyusun pengingat perawatan untuk tenant', [
                    'exception' => $e,
                    'tenant' => $tenant->slug,
                ]);
            }
        });

        // Lepaskan binding terakhir agar tidak bocor ke proses berikutnya.
        app()->forgetInstance('tenant');

        return self::SUCCESS;
    }
}
