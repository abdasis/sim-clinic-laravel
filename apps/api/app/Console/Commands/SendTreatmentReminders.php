<?php

namespace App\Console\Commands;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\Tenant;
use App\Services\BroadcastService;
use App\Support\CloudApiClient;
use App\Support\ReminderEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dijalankan scheduler tiap pagi: susun broadcast pengingat perawatan untuk
 * tiap tenant. Bila Cloud API siap dan templatenya sudah dipilih, langsung
 * diantrekan; bila belum, broadcast menunggu admin di menu Broadcast WA.
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

                if (app(CloudApiClient::class) instanceof CloudApiClient) {
                    $this->queueTodaysReminders($tenant);
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

    /**
     * Broadcast tanpa template Cloud API dilewati: mengantrekannya hanya
     * berujung penerima gagal satu per satu, dan abort 422-nya bisa
     * menjatuhkan sisa putaran command.
     */
    private function queueTodaysReminders(Tenant $tenant): void
    {
        Broadcast::query()
            ->where('kind', 'reminder')
            ->where('status', BroadcastStatus::Ready)
            ->whereDate('created_at', today())
            ->each(function (Broadcast $broadcast) use ($tenant): void {
                if ($broadcast->template === null) {
                    Log::warning('Pengingat perawatan dilewati karena template Cloud API belum dipilih.', [
                        'broadcast_id' => $broadcast->id,
                        'tenant' => $tenant->slug,
                    ]);

                    return;
                }

                app(BroadcastService::class)->queueSend($broadcast);
            });
    }
}
