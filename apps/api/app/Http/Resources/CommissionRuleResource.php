<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'type_label' => $this->type?->label(),
            'amount' => $this->amount,
            'percent' => $this->percent,
            'min_revenue' => $this->min_revenue,
            'is_active' => $this->is_active,
        ];
    }
}
