<?php

namespace App\Http\Resources;

use App\Models\ChatbotSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin ChatbotSetting
 */
class ChatbotSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_active' => (bool) $this->is_active,
            'allow_self_registration' => (bool) $this->allow_self_registration,
            'agent_name' => $this->agent_name,
            'closing_idle_minutes' => $this->closing_idle_minutes,
            'closing_message' => $this->closing_message,
            'agent_avatar_path' => $this->agent_avatar_path,
            'agent_avatar_url' => $this->agent_avatar_path
                ? Storage::disk('public')->url($this->agent_avatar_path)
                : null,
            'bookable_service_ids' => array_map('intval', $this->bookable_service_ids ?? []),
        ];
    }
}
