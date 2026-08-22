<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BroadcastResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'kind' => $this->kind,
            'kind_label' => $this->kind?->label(),
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'message' => $this->message,
            'audience' => $this->audience,
            'audience_label' => $this->audience?->label(),
            'audience_params' => $this->audience_params,
            'created_by_name' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            // Progres dihitung dari agregat withCount supaya daftar broadcast
            // tidak memuat ribuan baris penerima.
            'recipients_total' => $this->whenCounted('recipients'),
            'recipients_sent' => $this->whenCounted('sentRecipients'),
            'recipients_pending' => $this->whenCounted('pendingRecipients'),
            'recipients_failed' => $this->whenCounted('failedRecipients'),
            'recipients' => BroadcastRecipientResource::collection($this->whenLoaded('recipients')),
        ];
    }
}
