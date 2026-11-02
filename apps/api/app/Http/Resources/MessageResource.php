<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'direction_label' => $this->direction?->label(),
            'body' => $this->body,
            'media_type' => $this->media_type,
            'media_url' => $this->media_path === null
                ? null
                : Storage::disk('public')->url($this->media_path),
            'media_name' => $this->media_name,
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'reaction' => $this->reaction,
            'reply_to' => $this->whenLoaded('replyTo', fn (): ?array => $this->replyTo === null ? null : [
                'id' => $this->replyTo->id,
                'body' => $this->replyTo->body,
                'direction' => $this->replyTo->direction,
                'media_type' => $this->replyTo->media_type,
            ]),
            'provider_message_id' => $this->provider_message_id,
            'sent_at' => $this->sent_at?->toIso8601String(),
        ];
    }
}
