<?php

namespace App\Services;

use App\Actions\Backup\RunBackupAction;
use App\Actions\LogAuditAction;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Arsip cadangan basis data: daftar, buat, unduh, hapus.
 *
 * Daftarnya dibaca dari sistem berkas, bukan dari tabel. Yang menentukan
 * sebuah cadangan ada atau tidak adalah berkasnya sendiri — catatan yang
 * menyebut berkas yang sudah hilang justru menyesatkan saat dibutuhkan.
 */
class BackupService
{
    private const DISK = 'backups';

    /** Hanya berkas dump yang dibuat perintah ini yang diakui. */
    private const FILENAME = '/^[A-Za-z0-9_\-]+\.sql$/';

    /**
     * @return array<int, array{file: string, size: int, created_at: string, trigger: ?string}>
     */
    public function list(): array
    {
        $disk = Storage::disk(self::DISK);

        // files() sengaja tidak rekursif: arsip terjadwal milik paket lain
        // tinggal di subfolder sendiri dan bukan urusan halaman ini.
        $files = array_filter($disk->files(), fn (string $file): bool => $this->isBackupFile($file));
        $triggers = $this->triggers();

        $rows = array_map(fn (string $file): array => [
            'file' => $file,
            'size' => $disk->size($file),
            'created_at' => date(DATE_ATOM, $disk->lastModified($file)),
            // Berkasnya sendiri tidak menyimpan asal-usulnya; yang tahu hanya
            // catatan audit. Yang tidak ketemu dibiarkan null, bukan ditebak.
            'trigger' => $triggers[$file] ?? null,
        ], $files);

        // Terbaru di atas: yang dicari saat memulihkan hampir selalu yang
        // paling akhir dibuat.
        usort($rows, fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return array_values($rows);
    }

    /**
     * Peta nama berkas ke pemicunya, dari catatan audit pembuatan.
     *
     * @return array<string, string>
     */
    private function triggers(): array
    {
        return Activity::query()
            ->where('event', 'backup.created')
            ->latest('id')
            ->limit(200)
            ->get(['properties'])
            ->reduce(function (array $carry, Activity $activity): array {
                $file = $activity->properties['file'] ?? null;
                $trigger = $activity->properties['trigger'] ?? null;

                if (is_string($file) && is_string($trigger) && ! isset($carry[$file])) {
                    $carry[$file] = $trigger;
                }

                return $carry;
            }, []);
    }

    /**
     * @return array{file: ?string, path: ?string, size: int, created_at: ?string, error: ?string}
     */
    public function run(?User $causer, string $trigger = 'manual'): array
    {
        return app(RunBackupAction::class)->execute($causer, $trigger);
    }

    public function download(string $filename): StreamedResponse
    {
        $this->guard($filename);

        return Storage::disk(self::DISK)->download($filename);
    }

    public function delete(string $filename, ?User $causer): void
    {
        $this->guard($filename);

        $disk = Storage::disk(self::DISK);
        $size = $disk->exists($filename) ? $disk->size($filename) : 0;

        $disk->delete($filename);

        app(LogAuditAction::class)->handle(
            'backup.deleted',
            null,
            $causer,
            ['old' => ['file' => $filename, 'size_bytes' => $size]],
            'Menghapus arsip cadangan '.$filename.'.',
        );
    }

    /** Hapus arsip yang lebih tua dari N hari; kembalikan jumlahnya. */
    public function prune(int $keepDays = 30): int
    {
        $disk = Storage::disk(self::DISK);
        $cutoff = now()->subDays(max(1, $keepDays))->getTimestamp();
        $removed = 0;

        foreach ($disk->files() as $file) {
            if (! $this->isBackupFile($file) || $disk->lastModified($file) >= $cutoff) {
                continue;
            }

            $disk->delete($file);
            $removed++;
        }

        if ($removed > 0) {
            Log::info('Arsip cadangan lama dibersihkan.', ['removed' => $removed, 'keep_days' => $keepDays]);
        }

        return $removed;
    }

    /**
     * Nama berkas datang dari URL, jadi diperlakukan sebagai masukan asing:
     * hanya pola yang dihasilkan perintah cadangan yang diterima, dan itu
     * sekaligus menutup jalan "../" ke berkas mana pun di luar folder ini.
     */
    private function guard(string $filename): void
    {
        abort_unless($this->isBackupFile($filename), 404);
        abort_unless(Storage::disk(self::DISK)->exists($filename), 404);
    }

    private function isBackupFile(string $filename): bool
    {
        return preg_match(self::FILENAME, $filename) === 1;
    }
}
