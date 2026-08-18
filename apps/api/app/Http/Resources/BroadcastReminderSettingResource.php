<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BroadcastReminderSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'days' => $this->days,
            'message_template_id' => $this->message_template_id,
            'template_name' => $this->template?->name,
            'is_active' => $this->is_active,
        ];
    }
}
