<?php

namespace App\Http\Resources\CompanyProfile;

use App\Http\Resources\CompanyProfile\Concerns\PicksLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyBrandResource extends JsonResource
{
    use PicksLocale;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo_url' => $this->mediaUrl($this->logo_path),
            'url' => $this->url,
            'sort_order' => $this->sort_order,
        ];
    }
}
