<?php

namespace App\Http\Resources\CompanyProfile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyNavigationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'url' => $this->url,
            'link_type' => $this->link_type?->value,
            'position' => $this->position?->value,
            'is_cta' => (bool) $this->is_cta,
            'sort_order' => $this->sort_order,
        ];
    }
}
