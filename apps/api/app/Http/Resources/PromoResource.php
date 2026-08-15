<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_type_label' => $this->discount_type?->label(),
            'discount_value' => $this->discount_value,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            // Status jalan/belum/berakhir dihitung di server supaya semua
            // klien memakai tanggal yang sama.
            'is_running' => $this->isRunningOn(),
            'targets' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'type' => $item->promotable_type,
                'id' => $item->promotable_id,
                'name' => $item->promotable?->name,
            ])->values()),
            'targets_count' => $this->whenLoaded('items', fn () => $this->items->count()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
