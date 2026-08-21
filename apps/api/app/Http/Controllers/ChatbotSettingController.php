<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatbotAvatarRequest;
use App\Http\Requests\ChatbotSettingRequest;
use App\Http\Resources\ChatbotSettingResource;
use App\Models\ChatbotSetting;
use App\Services\ChatbotSettingService;
use Illuminate\Http\JsonResponse;

class ChatbotSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $this->authorize('viewAny', ChatbotSetting::class);

        $setting = ChatbotSetting::query()->first();

        return response()->json([
            // Belum pernah disimpan bukan keadaan galat: formulirnya butuh
            // bentuk yang sama supaya tidak perlu menebak di sisi frontend.
            'data' => $setting !== null
                ? new ChatbotSettingResource($setting)
                : [
                    'is_active' => false,
                    'agent_name' => null,
                    'agent_avatar_path' => null,
                    'agent_avatar_url' => null,
                    'bookable_service_ids' => [],
                    'allows_stock_info' => true,
                ],
            'meta' => [],
        ]);
    }

    public function update(ChatbotSettingRequest $request, ChatbotSettingService $settings): JsonResponse
    {
        $this->authorize('update', ChatbotSetting::class);

        $setting = $settings->save($request->validated());

        return response()->json([
            'data' => new ChatbotSettingResource($setting),
            'meta' => ['message' => __('chatbot.saved')],
        ]);
    }

    public function avatar(ChatbotAvatarRequest $request, ChatbotSettingService $settings): JsonResponse
    {
        $this->authorize('update', ChatbotSetting::class);

        $setting = $settings->uploadAvatar(app('tenant')->id, $request->file('file'));

        return response()->json([
            'data' => new ChatbotSettingResource($setting),
            'meta' => ['message' => __('chatbot.avatar_saved')],
        ]);
    }
}
