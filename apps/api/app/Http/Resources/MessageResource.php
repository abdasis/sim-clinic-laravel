<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'direction_label' => $this->direction?->label(),
            'body' => $this->body,
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'provider_message_id' => $this->provider_message_id,
            'sent_at' => $this->sent_at?->toIso8601String(),
        ];
    }
}
