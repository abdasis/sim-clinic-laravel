<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\IdleChatClosingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dijalankan scheduler tiap beberapa menit: tutup percakapan chatbot yang
 * ditinggal diam pasien, satu klinik demi satu.
 *
 * Frekuensinya rapat karena ambangnya dihitung dalam menit — pengecekan
 * sekali sehari akan mengirim pesan penutup berjam-jam setelah percakapannya
 * dingin, yang justru terbaca aneh.
 */
class CloseIdleChats extends Command
{
    protected $signature = 'clinic:close-idle-chats';

    protected $description = 'Kirim pesan penutup untuk percakapan chatbot yang tidak dibalas pasien';

    public function handle(IdleChatClosingService $service): int
    {
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use ($service): void {
            app()->instance('tenant', $tenant);

            try {
                $closed = $service->run();

                if ($closed > 0) {
                    $this->info("{$tenant->slug}: {$closed} percakapan ditutup.");
                }
            } catch (\Throwable $e) {
                Log::error('Gagal menutup percakapan chatbot yang menggantung.', [
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
