<?php

namespace App\Http\Resources\CompanyProfile;

use App\Http\Resources\CompanyProfile\Concerns\PicksLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyProfileSettingResource extends JsonResource
{
    use PicksLocale;

    public function toArray(Request $request): array
    {
        return [
            'is_published' => (bool) $this->is_published,
            'brand_name' => $this->text($request, $this->brand_name),
            'tagline' => $this->text($request, $this->tagline),
            'logo_url' => $this->mediaUrl($this->logo_path),
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'address' => $this->text($request, $this->address),
            'map_embed_url' => $this->map_embed_url,
            'social_links' => $this->social_links ?? [],
            'meta_title' => $this->text($request, $this->meta_title),
            'meta_description' => $this->text($request, $this->meta_description),
            'chat_widget_enabled' => (bool) $this->chat_widget_enabled,
            'chat_widget_number' => $this->chat_widget_number,
        ];
    }
}
