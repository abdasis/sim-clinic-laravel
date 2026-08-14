<?php

namespace App\Http\Resources\CompanyProfile;

use App\Http\Resources\CompanyProfile\Concerns\ExposesMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyBrandResource extends JsonResource
{
    use ExposesMedia;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->mediaUrl($this->logo_path),
            'external_url' => $this->external_url,
            'sort_order' => $this->sort_order,
        ];
    }
}
