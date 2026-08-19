<?php

namespace App\Actions\Chatbot;

use App\Actions\LogAuditAction;
use App\Models\ChatbotSetting;

/**
 * Simpan setelan chatbot klinik. Satu baris per klinik, jadi selalu upsert.
 */
class SaveChatbotSettingAction
{
    public function __construct(private readonly LogAuditAction $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): ChatbotSetting
    {
        $setting = ChatbotSetting::query()->first();
        $before = $setting?->only(['is_active', 'agent_name', 'bookable_service_ids']);

        if ($setting === null) {
            $setting = ChatbotSetting::create($attributes);
        } else {
            $setting->update($attributes);
        }

        $setting->refresh();

        $this->audit->handle(
            action: $before === null ? 'chatbot_setting.created' : 'chatbot_setting.updated',
            subject: $setting,
            context: $before === null
                ? ['attributes' => $setting->getAttributes()]
                : ['old' => $before, 'new' => $setting->only(['is_active', 'agent_name', 'bookable_service_ids'])],
            description: $setting->is_active
                ? 'Menyalakan chatbot WhatsApp dengan nama agen '.($setting->agent_name ?: 'bawaan').'.'
                : 'Mematikan chatbot WhatsApp.',
        );

        return $setting;
    }
}
