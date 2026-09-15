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
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            // Berapa pasien yang memegangnya — layar memakainya untuk
            // menjelaskan kenapa sebuah tingkat tidak bisa dihapus.
            'patients_count' => $this->whenCounted('patients'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
