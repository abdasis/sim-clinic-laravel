<?php

namespace App\Http\Resources\CompanyProfile;

use App\Http\Resources\CompanyProfile\Concerns\PicksLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyValuePropResource extends JsonResource
{
    use PicksLocale;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'icon' => $this->icon,
            'title' => $this->text($request, $this->title),
            // Rich text Tiptap; frontend yang merendernya jadi HTML.
            'description' => $this->text($request, $this->description),
            'sort_order' => $this->sort_order,
        ];
    }
}
