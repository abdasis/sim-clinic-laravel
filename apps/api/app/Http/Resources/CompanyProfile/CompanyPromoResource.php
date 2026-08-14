<?php

namespace App\Http\Resources\CompanyProfile;

use App\Http\Resources\CompanyProfile\Concerns\PicksLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyPromoResource extends JsonResource
{
    use PicksLocale;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->text($request, $this->title),
            'description' => $this->text($request, $this->description),
            'image_url' => $this->mediaUrl($this->image_path),
            'cta_label' => $this->text($request, $this->cta_label),
            'cta_type' => $this->cta_type?->value,
            'cta_value' => $this->cta_value,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'sort_order' => $this->sort_order,
        ];
    }
}
