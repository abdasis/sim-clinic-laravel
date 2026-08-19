<?php

namespace App\Actions\Chatbot;

use App\Actions\LogAuditAction;
use App\Models\ChatbotSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Simpan avatar agen chatbot ke disk publik, dipisah per klinik.
 */
class UploadChatbotAvatarAction
{
    public function __construct(private readonly LogAuditAction $audit) {}

    public function handle(int $tenantId, UploadedFile $file): ChatbotSetting
    {
        try {
            $path = $file->store("chatbot/{$tenantId}/avatar", 'public');
        } catch (Throwable $e) {
            Log::error('Gagal mengunggah avatar chatbot.', [
                'exception' => $e,
                'tenant_id' => $tenantId,
                'file' => $file->getClientOriginalName(),
            ]);

            throw $e;
        }

        $setting = ChatbotSetting::query()->first();

        if ($setting === null) {
            $setting = ChatbotSetting::create(['agent_avatar_path' => $path]);
        } else {
            $setting->update(['agent_avatar_path' => $path]);
        }

        $this->audit->handle(
            action: 'chatbot_setting.avatar_uploaded',
            subject: $setting,
            context: ['attributes' => ['agent_avatar_path' => $path, 'original_name' => $file->getClientOriginalName(), 'size' => $file->getSize()]],
            description: 'Mengunggah avatar agen chatbot ('.$path.').',
        );

        return $setting->refresh();
    }
}
