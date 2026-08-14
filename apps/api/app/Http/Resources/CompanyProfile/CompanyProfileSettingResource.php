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
            'copyright_text' => $this->copyright_text,
            'chat_channels' => $this->chat_channels ?? [],
            'social_links' => $this->social_links ?? [],
            'marketplace_links' => $this->marketplace_links ?? [],
            'default_locale' => $this->default_locale,
            'is_published' => (bool) $this->is_published,
        ];
    }
}
