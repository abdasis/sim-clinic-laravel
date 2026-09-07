<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Bagikan seluruh grup terjemahan ke frontend SPA (CLAUDE.md i18n).
 * Pengganti share HandleInertiaRequests untuk arsitektur Sanctum SPA.
 */
class TranslationController extends Controller
{
    /**
     * Bahasa yang punya berkas terjemahan lengkap. Nilai di luar daftar
     * diabaikan supaya `?locale=` tidak bisa dipakai menebak berkas lain.
     */
    private const LOCALES = ['id', 'en'];

    public function index(Request $request): JsonResponse
    {
        $locale = $request->query('locale');
        $locale = in_array($locale, self::LOCALES, true) ? $locale : app()->getLocale();

        $translations = [];

        // Grup ditemukan dari berkasnya, bukan daftar manual: dulu daftar
        // manual pernah tertinggal saat modul baru lahir dan key mentah
        // seperti "expense.title" bocor ke layar pengguna.
        foreach ($this->groups() as $group) {
            $translations[$group] = __($group, [], $locale);
        }

        return response()->json([
            'data' => $translations,
            // Sidik isi terjemahan, dipakai frontend untuk tahu apakah salinan
            // yang disimpannya masih sama. Tanpa penanda ini, salinan lokal
            // yang basi tidak akan pernah ketahuan basinya.
            'meta' => ['locale' => $locale, 'version' => $this->version($translations)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private function version(array $translations): string
    {
        return substr(md5(json_encode($translations) ?: ''), 0, 12);
    }

    /**
     * @return array<int, string>
     */
    private function groups(): array
    {
        $groups = [];

        foreach (self::LOCALES as $locale) {
            $path = lang_path($locale);

            if (! File::isDirectory($path)) {
                continue;
            }

            foreach (File::files($path) as $file) {
                if ($file->getExtension() === 'php') {
                    $groups[] = $file->getFilenameWithoutExtension();
                }
            }
        }

        sort($groups);

        return array_values(array_unique($groups));
    }
}
