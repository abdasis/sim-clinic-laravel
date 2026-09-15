<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipTierResource extends JsonResource
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
            'stacks_with_promo' => (bool) $this->stacks_with_promo,
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            // Berapa pasien yang memegangnya — layar memakainya untuk
            // menjelaskan kenapa sebuah tingkat tidak bisa dihapus.
            'patients_count' => $this->whenCounted('patients'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
