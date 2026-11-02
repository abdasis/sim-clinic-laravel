<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_phone' => $this->contact_phone,
            'contact_name' => $this->contact_name,
            'channel' => $this->channel,
            'channel_label' => $this->channel?->label(),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_message_body' => $this->latestMessage?->body,
            'unread_count' => $this->unread_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
