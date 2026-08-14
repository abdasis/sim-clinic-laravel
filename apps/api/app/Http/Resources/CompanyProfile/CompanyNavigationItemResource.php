<?php

namespace App\Http\Resources\CompanyProfile;

use App\Http\Resources\CompanyProfile\Concerns\PicksLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyNavigationItemResource extends JsonResource
{
    use PicksLocale;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->text($request, $this->label),
            'position' => $this->position?->value,
            'link_type' => $this->link_type?->value,
            'link_value' => $this->link_value,
            'sort_order' => $this->sort_order,
        ];
    }
}
