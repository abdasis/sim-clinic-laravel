<?php

namespace App\Http\Resources\CompanyProfile\Concerns;

use App\Support\LocaleText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Perkakas bersama resource company profile: memilih bahasa dari request dan
 * mengubah path storage jadi URL yang bisa dipakai browser.
 */
trait PicksLocale
{
    protected function locale(Request $request): string
    {
        $locale = $request->query('locale');

        return in_array($locale, ['id', 'en'], true) ? $locale : LocaleText::FALLBACK;
    }

    /**
     * @param  array<string, mixed>|string|null  $value
     */
    protected function text(Request $request, array|string|null $value): mixed
    {
        return LocaleText::pick($value, $this->locale($request));
    }

    protected function mediaUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
