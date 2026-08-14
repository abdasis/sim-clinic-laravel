<?php

namespace App\Http\Resources\CompanyProfile;

use App\Http\Resources\CompanyProfile\Concerns\PicksLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyContentSectionResource extends JsonResource
{
    use PicksLocale;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_key' => $this->section_key?->value,
            'layout' => $this->layout?->value,
            'title' => $this->text($request, $this->title),
            'body' => $this->text($request, $this->body),
            'image_url' => $this->mediaUrl($this->image_path),
            'cta_label' => $this->text($request, $this->cta_label),
            'cta_type' => $this->cta_type?->value,
            'cta_value' => $this->cta_value,
        ];
    }
}
