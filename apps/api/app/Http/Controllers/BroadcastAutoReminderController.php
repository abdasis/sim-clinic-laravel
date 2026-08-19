<?php

namespace App\Http\Controllers;

use App\Http\Requests\AutoReminderSettingRequest;
use App\Http\Resources\BroadcastReminderSettingResource;
use App\Models\Broadcast;
use App\Models\BroadcastReminderSetting;
use App\Services\BroadcastService;
use Illuminate\Http\JsonResponse;

/**
 * Pengaturan follow-up otomatis klinik: satu ambang hari untuk seluruh pasien,
 * dihitung dari rekam medis terakhir. Scheduler harian yang memakainya.
 */
class BroadcastAutoReminderController extends Controller
{
    public function show(): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        $setting = BroadcastReminderSetting::query()->with('template:id,name')->first();

        return response()->json([
            // Belum diatur bukan keadaan galat: formulirnya perlu bentuk yang
            // sama supaya tidak perlu menebak-nebak di sisi frontend.
            'data' => $setting !== null
                ? new BroadcastReminderSettingResource($setting)
                : ['days' => null, 'message_template_id' => null, 'template_name' => null, 'is_active' => false],
            'meta' => [],
        ]);
    }

    public function update(AutoReminderSettingRequest $request, BroadcastService $broadcasts): JsonResponse
    {
        $this->authorize('update', Broadcast::class);

        $setting = $broadcasts->saveAutoReminderSetting($request->validated());

        return response()->json([
            'data' => new BroadcastReminderSettingResource($setting),
            'meta' => ['message' => __('broadcast.auto_reminder_saved')],
        ]);
    }
}
