<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Frontend selalu berbahasa Indonesia, jadi kamusnya dibundel saat build
 * alih-alih diambil dari API. Command ini menghasilkan berkas itu dari
 * `lang/id`, sehingga modul baru otomatis ikut tanpa daftar manual.
 */
class ExportWebTranslationsCommand extends Command
{
    protected $signature = 'translations:export {--path= : Tujuan berkas JSON (bawaan: apps/web/src/lib/translations-id.json)}';

    protected $description = 'Ekspor kamus terjemahan Indonesia ke berkas JSON untuk frontend';

    public function handle(): int
    {
        $path = $this->option('path') ?: base_path('../web/src/lib/translations-id.json');

        $json = json_encode(
            $this->translations(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            $this->error('Gagal menyandikan terjemahan ke JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        try {
            File::ensureDirectoryExists(dirname($path));

            if (File::put($path, $json.PHP_EOL) === false) {
                $this->error("Gagal menulis kamus terjemahan ke {$path}.");

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error("Gagal menulis kamus terjemahan ke {$path}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Kamus terjemahan Indonesia diekspor ke {$path}.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function translations(): array
    {
        $translations = [];

        // Grup ditemukan dari berkasnya, bukan daftar manual: daftar manual
        // pernah tertinggal saat modul baru lahir.
        foreach ($this->groups() as $group) {
            $translations[$group] = __($group, [], 'id');
        }

        return $translations;
    }

    /**
     * @return array<int, string>
     */
    private function groups(): array
    {
        $path = lang_path('id');

        if (! File::isDirectory($path)) {
            return [];
        }

        $groups = [];

        foreach (File::files($path) as $file) {
            if ($file->getExtension() === 'php') {
                $groups[] = $file->getFilenameWithoutExtension();
            }
        }

        sort($groups);

        return array_values(array_unique($groups));
    }
}
