<?php

namespace App\Http\Resources;

use App\Models\BookingReminderSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingReminderSetting
 */
class BookingReminderSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_active' => (bool) $this->is_active,
            'offset_minutes' => (int) $this->offset_minutes,
            'message_template_id' => $this->message_template_id,
            'template_name' => $this->template?->name,
        ];
    }
}
