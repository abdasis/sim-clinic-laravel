<?php

namespace App\Http\Resources\CompanyProfile;

use App\Http\Resources\CompanyProfile\Concerns\ExposesMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyProfileSettingResource extends JsonResource
{
    use ExposesMedia;

    public function toArray(Request $request): array
    {
        return [
            'site_name' => $this->site_name,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->mediaUrl($this->logo_path),
            'address' => $this->address,
            'maps_url' => $this->maps_url,
            // Nomor klinik tinggal di tabel tenant, bukan di setelan profil.
            // Kaki halaman publik memerlukannya bersama alamat, jadi ikut
            // dikirim di sini alih-alih memaksa satu request lagi.
            'phone' => app()->bound('tenant') ? app('tenant')->phone : null,
            'tagline' => $this->tagline,
            'operating_hours' => $this->operating_hours,
            'receipt_note' => $this->receipt_note,
            'copyright_text' => $this->copyright_text,
            'chat_channels' => $this->chat_channels ?? [],
            'social_links' => $this->social_links ?? [],
            'marketplace_links' => $this->marketplace_links ?? [],
            'default_locale' => $this->default_locale,
            'is_published' => (bool) $this->is_published,
        ];
    }
}
