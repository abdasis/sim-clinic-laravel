<?php

namespace App\Http\Resources\CompanyProfile;

use App\Http\Resources\CompanyProfile\Concerns\PicksLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTestimonialResource extends JsonResource
{
    use PicksLocale;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_name' => $this->author_name,
            'author_role' => $this->text($request, $this->author_role),
            'quote' => $this->text($request, $this->quote),
            'avatar_url' => $this->mediaUrl($this->avatar_path),
            'rating' => $this->rating,
            'sort_order' => $this->sort_order,
        ];
    }
}
