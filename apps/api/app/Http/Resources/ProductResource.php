<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'unit' => $this->unit,
            'stock_balance' => $this->stock_balance,
            'min_threshold' => $this->min_threshold,
            'price' => $this->price,
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'is_low_stock' => $this->is_low_stock,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
