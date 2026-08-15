<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BroadcastRecipientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'message' => $this->message,
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'error' => $this->error,
        ];
    }
}
