<?php

namespace App\Http\Resources;

use App\Support\StaffReferences;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'clinic_role' => $this->clinic_role,
            'clinic_role_label' => $this->clinic_role?->label(),
            'status' => $this->status,
            // Staf yang sudah mencatat data tidak boleh dihapus permanen;
            // penilaiannya di server supaya tombolnya tidak menjanjikan
            // sesuatu yang nanti ditolak.
            'can_delete' => ! $this->references($request)->has($this->id),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function references(Request $request): StaffReferences
    {
        $references = $request->attributes->get('staff_references');

        return $references instanceof StaffReferences ? $references : new StaffReferences;
    }
}
