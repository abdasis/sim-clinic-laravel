<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'patient_id' => $this->patient_id,
            'author_id' => $this->author_id,
            'author_name' => $this->author?->name,
            'subjective' => $this->subjective,
            'objective' => $this->objective,
            'assessment' => $this->assessment,
            'plan' => $this->plan,
            'treatments' => $this->treatmentRecords->map(fn ($treatment) => [
                'id' => $treatment->id,
                'service_name' => $treatment->service_name,
                'notes' => $treatment->notes,
            ]),
            'photos' => $this->medicalPhotos->map(fn ($photo) => [
                'id' => $photo->id,
                'type' => $photo->type,
                'type_label' => $photo->type?->label(),
                'path' => $photo->path,
                'url' => $photo->url,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
