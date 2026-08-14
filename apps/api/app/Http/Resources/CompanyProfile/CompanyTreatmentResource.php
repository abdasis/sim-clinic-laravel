<?php

namespace App\Http\Resources\CompanyProfile;

use App\Http\Resources\CompanyProfile\Concerns\ExposesMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTreatmentResource extends JsonResource
{
    use ExposesMedia;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'image_url' => $this->mediaUrl($this->image_path),
            'badge' => $this->badge?->value,
            'badge_label' => $this->badge?->label(),
            'category_tags' => $this->category_tags ?? [],
            'detail_url' => $this->detail_url,
            'service_id' => $this->service_id,
            'sort_order' => $this->sort_order,
        ];
    }
}
