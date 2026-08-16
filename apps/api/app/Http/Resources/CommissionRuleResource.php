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
            'therapist_id' => $this->therapist_id,
            'therapist_name' => $this->therapist?->name,
            'type' => $this->type,
            'type_label' => $this->type?->label(),
            'amount' => $this->amount,
            'percent' => $this->percent,
            'min_revenue' => $this->min_revenue,
            'is_active' => $this->is_active,
            // Null pada tarif perdana: berlaku sejak awal, tidak perlu
            // ditampilkan sebagai tanggal apa pun.
            'effective_from' => $this->effective_from?->toIso8601String(),
        ];
    }
}
