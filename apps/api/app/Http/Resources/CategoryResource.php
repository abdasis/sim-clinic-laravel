<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'type_label' => $this->type?->label(),
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            // Jumlah pemakaian dimuat lewat withCount di controller; tanpa itu
            // daftar kategori akan menembak satu kueri per baris.
            'usage_count' => $this->whenCounted('categorizables'),
        ];
    }
}
