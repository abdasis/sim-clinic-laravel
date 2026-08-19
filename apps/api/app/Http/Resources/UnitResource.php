<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            // Dimuat lewat withCount di controller; tanpa itu daftar satuan
            // menembak satu kueri per baris.
            'usage_count' => $this->whenCounted('products'),
        ];
    }
}
