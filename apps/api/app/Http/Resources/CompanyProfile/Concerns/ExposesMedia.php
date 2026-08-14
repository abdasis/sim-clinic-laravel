<?php

namespace App\Http\Resources\CompanyProfile\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Kontrak publik mengirim `image_path` apa adanya (spec 010), tapi browser
 * butuh URL siap pakai — keduanya dikirim supaya frontend tidak perlu tahu
 * konvensi disk.
 */
trait ExposesMedia
{
    protected function mediaUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
