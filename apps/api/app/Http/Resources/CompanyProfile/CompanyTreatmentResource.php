<?php

namespace App\Http\Resources\CompanyProfile;

use App\Http\Resources\CompanyProfile\Concerns\PicksLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTreatmentResource extends JsonResource
{
    use PicksLocale;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->text($request, $this->name),
            'excerpt' => $this->text($request, $this->excerpt),
            'description' => $this->text($request, $this->description),
            'image_url' => $this->mediaUrl($this->image_path),
            'badge' => $this->badge?->value,
            'badge_label' => $this->badge?->label(),
            'price_label' => $this->price_label,
            'service_id' => $this->service_id,
            'sort_order' => $this->sort_order,
        ];
    }
}
