<?php

namespace App\Http\Resources;

use App\Http\Resources\CompanyProfile\Concerns\ExposesMedia;
use App\Models\Tenant;
use App\Support\LocaleText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kop nota: nama, kontak, dan logo klinik yang sedang aktif. Membungkus
 * Tenant, bukan setelan profil, supaya nama dan telepon tetap terkirim
 * walau tenant belum pernah mengisi profil perusahaannya.
 *
 * @property Tenant $resource
 */
class ClinicIdentityResource extends JsonResource
{
    use ExposesMedia;

    public function toArray(Request $request): array
    {
        $profile = $this->companyProfile;

        return [
            // Slug permanen — sidebar memakainya untuk menautkan brand ke
            // beranda kliniknya sendiri, bukan menebak dari URL yang aktif.
            'slug' => $this->slug,
            'name' => LocaleText::pick($profile?->site_name, app()->getLocale()) ?: $this->name,
            'tagline' => $profile?->tagline,
            'address' => $profile?->address,
            'phone' => $this->phone,
            'logo_url' => $this->mediaUrl($profile?->logo_path),
            'receipt_note' => $profile?->receipt_note,
        ];
    }
}
