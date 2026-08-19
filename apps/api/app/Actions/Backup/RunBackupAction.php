<?php

namespace App\Actions\Backup;

use App\Actions\LogAuditAction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Jalankan `pg_dump` dan simpan hasilnya sebagai satu berkas .sql.
 *
 * Kata sandi dilewatkan lewat environment, bukan argumen baris perintah:
 * argumen terbaca siapa pun yang menjalankan `ps` di mesin yang sama.
 */
class RunBackupAction
{
    /** Basis data klinik tumbuh pelan; sepuluh menit sudah sangat lapang. */
    private const TIMEOUT = 600;

    /**
     * @return array{file: ?string, path: ?string, size: int, created_at: ?string, error: ?string}
     */
    public function execute(?User $causer = null, string $trigger = 'manual'): array
    {
        $connection = config('database.connections.pgsql');
        $directory = storage_path('app/backups');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return $this->fail('Direktori cadangan tidak bisa dibuat.', ['directory' => $directory]);
        }

        $filename = 'sim_clinic_laravel_'.now()->format('Ymd_His').'.sql';
        $path = $directory.'/'.$filename;

        $process = new Process(
            [
                'pg_dump',
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--username='.$connection['username'],
                '--dbname='.$connection['database'],
                '--no-owner',
                '--no-privileges',
                '--file='.$path,
            ],
            env: ['PGPASSWORD' => (string) $connection['password']],
            timeout: self::TIMEOUT,
        );

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            return $this->fail('Proses pencadangan melewati batas waktu.', ['exception' => $e]);
        }

        if (! $process->isSuccessful()) {
            // Berkas separuh jadi lebih berbahaya daripada tidak ada berkas:
            // ia terlihat seperti cadangan yang sah sampai saat dipulihkan.
            @unlink($path);

            return $this->fail('Gagal mencadangkan database.', [
                'output' => $process->getErrorOutput(),
                'exit_code' => $process->getExitCode(),
            ]);
        }

        $size = (int) (filesize($path) ?: 0);

        app(LogAuditAction::class)->handle(
            'backup.created',
            null,
            $causer,
            ['trigger' => $trigger, 'file' => $filename, 'size_bytes' => $size],
            'Mencadangkan database (pemicu: '.($trigger === 'manual' ? 'manual' : 'jadwal').') — berkas '.$filename.' dibuat.',
        );

        return [
            'file' => $filename,
            'path' => $path,
            'size' => $size,
            'created_at' => now()->toIso8601String(),
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{file: null, path: null, size: int, created_at: null, error: string}
     */
    private function fail(string $message, array $context): array
    {
        Log::error($message, $context);

        return ['file' => null, 'path' => null, 'size' => 0, 'created_at' => null, 'error' => $message];
    }
}
