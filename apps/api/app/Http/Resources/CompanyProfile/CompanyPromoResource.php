<?php

namespace App\Http\Resources\CompanyProfile;

use App\Http\Resources\CompanyProfile\Concerns\ExposesMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyPromoResource extends JsonResource
{
    use ExposesMedia;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'image_url' => $this->mediaUrl($this->image_path),
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'cta_type' => $this->cta_type?->value,
            'sort_order' => $this->sort_order,
        ];
    }
}
