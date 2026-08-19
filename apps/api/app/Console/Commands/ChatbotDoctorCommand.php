<?php

namespace App\Console\Commands;

use App\Models\ChatbotSetting;
use App\Models\Tenant;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Periksa satu per satu apa yang dibutuhkan chatbot agar bisa membalas.
 *
 * Rantainya panjang — token, sesi, antrian, kunci AI, saklar per klinik — dan
 * tiap mata rantai yang putus menghasilkan gejala yang sama persis: pesan
 * masuk, tidak ada balasan, log bersih. Perintah ini yang menyebutkan mata
 * rantai mana yang sebenarnya putus, supaya tidak perlu ditebak satu-satu.
 */
class ChatbotDoctorCommand extends Command
{
    protected $signature = 'chatbot:doctor';

    protected $description = 'Periksa kesiapan chatbot WhatsApp: token, gateway, antrian, kunci AI, dan saklar tiap klinik';

    private bool $blocked = false;

    public function handle(): int
    {
        $this->info('Memeriksa kesiapan chatbot WhatsApp...');
        $this->newLine();

        $this->checkPlatform();
        $this->newLine();
        $this->checkTenants();
        $this->newLine();

        if ($this->blocked) {
            $this->error('Ada yang masih menghalangi chatbot membalas. Perbaiki baris bertanda GAGAL di atas.');

            return self::FAILURE;
        }

        $this->info('Semua siap. Kalau pesan masih tidak dibalas, periksa apakah webhook sudah terdaftar di gateway (php artisan chatbot:setup-webhook) dan lihat log setelah pasien mengirim pesan.');

        return self::SUCCESS;
    }

    /** Hal-hal yang berlaku untuk seluruh klinik sekaligus. */
    private function checkPlatform(): void
    {
        $this->line('<comment>Platform</comment>');

        $this->check(
            'Token webhook (WAHA_WEBHOOK_TOKEN)',
            filled(config('waha.webhook_token')),
            'kosong — webhook menolak semua pesan masuk dengan 404',
        );

        $this->check(
            'Kunci AI (DEEPSEEK_API_KEY)',
            filled(config('services.deepseek.api_key')),
            'kosong — chatbot tidak akan menjawab apa pun',
        );

        $this->check(
            'Tabel chatbot sudah dimigrasi',
            Schema::hasTable('chatbot_settings') && Schema::hasTable('chat_messages'),
            'belum ada — jalankan php artisan migrate',
        );

        $server = WahaSetting::query()->first();
        $this->check(
            'Server gateway WhatsApp tersetel',
            $server !== null && $server->isConfigured(),
            'alamat atau API key belum diisi di panel platform',
        );

        $this->checkQueue();
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

    /** Saklar dan sesi milik tiap klinik. */
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

            $session = WhatsappSetting::query()->first();
            $chatbot = ChatbotSetting::query()->first();

            $problems = [];

            if ($session === null || ! $session->isConfigured()) {
                $problems[] = 'nama sesi gateway belum diisi';
            }

            if ($chatbot === null) {
                $problems[] = 'chatbot belum pernah disetel';
            } elseif (! $chatbot->is_active) {
                $problems[] = 'chatbot masih dimatikan';
            }

            if ($problems === []) {
                $this->line('  <fg=green>OK</>        '.$tenant->slug.' — sesi "'.$session->session.'", chatbot aktif');

                continue;
            }

            $this->blocked = true;
            $this->line('  <fg=red>GAGAL</>     '.$tenant->slug.' — '.implode('; ', $problems));
        }
    }

    private function check(string $label, bool $passed, string $failure): void
    {
        if ($passed) {
            $this->line('  <fg=green>OK</>        '.$label);

            return;
        }

        $this->blocked = true;
        $this->line('  <fg=red>GAGAL</>     '.$label.': '.$failure);
    }
}
