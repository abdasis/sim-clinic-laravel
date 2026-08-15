<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'spent_at' => $this->spent_at?->toDateString(),
            'category' => $this->category,
            'category_label' => $this->category?->label(),
            'description' => $this->description,
            'amount' => $this->amount,
            'note' => $this->note,
            'recorded_by_name' => $this->recorder?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
