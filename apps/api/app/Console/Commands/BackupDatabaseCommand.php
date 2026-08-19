<?php

namespace App\Console\Commands;

use App\Actions\Backup\RunBackupAction;
use App\Services\BackupService;
use Illuminate\Console\Command;

/**
 * Cadangan basis data lewat baris perintah.
 *
 * Isinya seluruh klinik sekaligus — satu basis data untuk semua tenant — jadi
 * berkasnya tidak boleh sampai ke tangan admin klinik mana pun.
 *
 * ponytail: mengandalkan `pg_dump` yang tersedia di PATH. Di Docker, image
 * container API wajib memasang `postgresql-client`, dan versi mayornya
 * sebaiknya sama dengan server basis datanya.
 */
class BackupDatabaseCommand extends Command
{
    protected $signature = 'clinic:backup {--prune= : Hapus arsip yang lebih tua dari N hari}';

    protected $description = 'Cadangkan basis data ke storage/app/backups';

    public function handle(BackupService $backups): int
    {
        $result = app(RunBackupAction::class)->execute(null, 'schedule');

        if ($result['error'] !== null) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $this->info('Cadangan dibuat: '.$result['file'].' ('.$result['size'].' bita).');

        $prune = $this->option('prune');

        if ($prune !== null && $prune !== '') {
            $removed = $backups->prune((int) $prune);

            $this->info($removed.' arsip lama dihapus.');
        }

        return self::SUCCESS;
    }
}
