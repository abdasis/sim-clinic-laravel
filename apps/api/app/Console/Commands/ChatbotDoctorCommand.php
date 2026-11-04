<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\ChatbotDiagnostics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Periksa satu per satu apa yang dibutuhkan chatbot agar bisa membalas.
 *
 * Rantainya panjang — kunci AI, gateway, sesi, webhook, antrian, saklar per
 * klinik — dan tiap mata rantai yang putus menghasilkan gejala yang sama
 * persis: pesan masuk, tidak ada balasan, log bersih. Perintah ini memakai
 * pemeriksaan yang sama dengan halaman diagnosa supaya laporan terminal dan
 * layar tidak pernah berbeda.
 */
class ChatbotDoctorCommand extends Command
{
    protected $signature = 'chatbot:doctor';

    protected $description = 'Periksa kesiapan chatbot WhatsApp: gateway, sesi, webhook, antrian, kunci AI, dan saklar tiap klinik';

    private bool $blocked = false;

    public function handle(): int
    {
        $this->info('Memeriksa kesiapan chatbot WhatsApp...');
        $this->newLine();

        if (! $this->tablesExist()) {
            return self::FAILURE;
        }

        $this->checkQueue();
        $this->newLine();
        $this->checkTenants();
        $this->newLine();

        if ($this->blocked) {
            $this->error('Ada yang masih menghalangi chatbot membalas. Perbaiki baris bertanda GAGAL di atas.');

            return self::FAILURE;
        }

        $this->info('Semua siap. Kalau pesan masih tidak dibalas, pastikan webhook sudah terdaftar di gateway dan lihat log setelah pasien mengirim pesan.');

        return self::SUCCESS;
    }

    private function tablesExist(): bool
    {
        $ready = Schema::hasTable('chatbot_settings')
            && Schema::hasTable('chat_messages')
            && Schema::hasTable('whatsapp_settings');

        if (! $ready) {
            $this->blocked = true;
            $this->line('<fg=red>GAGAL</>     Tabel chatbot belum dimigrasi — jalankan php artisan migrate');
        }

        return $ready;
    }

    /**
     * Antrian adalah penyebab paling sering dan paling sunyi: pesannya
     * diterima, job-nya dibuat, lalu tidak ada yang menjalankannya.
     */
    private function checkQueue(): void
    {
        $driver = config('queue.default');

        if ($driver === 'sync') {
            $this->line('  <fg=yellow>PERHATIAN</> Antrian: driver sync — balasan disusun langsung di dalam webhook, dan gateway bisa keburu timeout.');

            return;
        }

        $this->line('  <fg=blue>INFO</>      Antrian: driver '.$driver.' — proses queue:work WAJIB berjalan, kalau tidak job hanya menumpuk.');

        if ($driver !== 'database' || ! Schema::hasTable('jobs')) {
            return;
        }

        $waiting = DB::table('jobs')->count();

        if ($waiting > 0) {
            $this->blocked = true;
            $this->line('  <fg=red>GAGAL</>     '.$waiting.' job menunggu di antrian dan tidak ada yang mengambilnya. Pastikan container/proses queue:work berjalan.');
        }
    }

    /** Saklar, kredensial gateway, sesi, dan webhook milik tiap klinik. */
    private function checkTenants(): void
    {
        $this->line('<comment>Per klinik</comment>');

        $tenants = Tenant::query()->where('status', 'active')->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->line('  <fg=yellow>PERHATIAN</> Tidak ada klinik aktif.');

            return;
        }

        foreach ($tenants as $tenant) {
            app()->instance('tenant', $tenant);

            $report = app(ChatbotDiagnostics::class)->run();
            $broken = collect($report['checks'])->where('ok', false);

            if ($broken->isEmpty()) {
                $this->line('  <fg=green>OK</>        '.$tenant->slug);

                continue;
            }

            $this->blocked = true;
            $reasons = $broken
                ->map(fn (array $check) => $check['key'].': '.$check['hint'])
                ->implode('; ');

            $this->line('  <fg=red>GAGAL</>     '.$tenant->slug.' — '.$reasons);
        }
    }
}
