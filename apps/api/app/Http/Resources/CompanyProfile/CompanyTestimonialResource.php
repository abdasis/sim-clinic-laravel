<?php

namespace App\Http\Resources\CompanyProfile;

use App\Http\Resources\CompanyProfile\Concerns\ExposesMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTestimonialResource extends JsonResource
{
    use ExposesMedia;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quote' => $this->quote,
            'author_name' => $this->author_name,
            'since_year' => $this->since_year,
            'avatar_path' => $this->avatar_path,
            'avatar_url' => $this->mediaUrl($this->avatar_path),
            'sort_order' => $this->sort_order,
        ];
    }
}
