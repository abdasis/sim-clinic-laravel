<?php

namespace App\Services;

use App\Actions\Chatbot\SaveChatbotSettingAction;
use App\Actions\Chatbot\UploadChatbotAvatarAction;
use App\Models\ChatbotSetting;
use Illuminate\Http\UploadedFile;

/**
 * Use case pengaturan chatbot. Dipisah dari ChatbotService yang mengurus
 * percakapan: menyimpan setelan harus tetap bisa walau penyedia AI belum
 * disetel sama sekali.
 */
class ChatbotSettingService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(array $attributes): ChatbotSetting
    {
        return app(SaveChatbotSettingAction::class)->handle($attributes);
    }

    public function uploadAvatar(int $tenantId, UploadedFile $file): ChatbotSetting
    {
        return app(UploadChatbotAvatarAction::class)->handle($tenantId, $file);
    }
}
