<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\WahaClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daftarkan alamat webhook pesan masuk ke sesi WhatsApp tiap klinik.
 *
 * Dijalankan sekali setelah deploy, dan lagi setiap kali APP_URL atau token
 * webhook berubah. Tanpa ini gateway tidak tahu ke mana pesan masuk dikirim,
 * dan chatbot yang sudah dinyalakan admin tetap membisu tanpa penjelasan.
 */
class SetupChatbotWebhookCommand extends Command
{
    protected $signature = 'chatbot:setup-webhook';

    protected $description = 'Daftarkan URL webhook pesan masuk WhatsApp untuk seluruh klinik aktif';

    public function handle(): int
    {
        $token = config('waha.webhook_token');

        if (blank($token)) {
            $this->error('WAHA_WEBHOOK_TOKEN belum disetel; webhook tidak bisa didaftarkan.');

            return self::FAILURE;
        }

        $url = rtrim((string) config('app.url'), '/').'/api/whatsapp/webhook/'.$token;
        $done = 0;
        $skipped = 0;

        Tenant::query()->where('status', 'active')->orderBy('id')->each(
            function (Tenant $tenant) use ($url, &$done, &$skipped): void {
                app()->instance('tenant', $tenant);
                // Binding-nya membaca setelan per klinik, jadi harus dilepas
                // supaya klinik berikutnya tidak memakai sesi klinik sebelumnya.
                app()->forgetInstance(WahaClient::class);

                $client = app(WahaClient::class);

                if (! $client instanceof WahaClient) {
                    $this->line("  - {$tenant->slug}: gateway belum disetel, dilewati");
                    $skipped++;

                    return;
                }

                try {
                    $client->setWebhook($url);
                    $this->info("  - {$tenant->slug}: webhook terdaftar");
                    $done++;
                } catch (Throwable $e) {
                    Log::error('Gagal mendaftarkan webhook chatbot.', [
                        'exception' => $e,
                        'tenant_id' => $tenant->id,
                    ]);
                    $this->error("  - {$tenant->slug}: gagal — {$e->getMessage()}");
                    $skipped++;
                }
            }
        );

        $this->newLine();
        $this->info("Selesai: {$done} klinik terdaftar, {$skipped} dilewati.");
        // Saringan bawaan gateway ikut terpasang di call yang sama, jadi sesi
        // lama baru berhenti mengirim status dan grup setelah command ini
        // dijalankan ulang. Disebut di sini supaya tidak perlu ditebak.
        $this->line('Status, grup, saluran, dan daftar siaran ikut disaring di gateway.');

        return self::SUCCESS;
    }
}
