<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingReminderSettingRequest;
use App\Http\Resources\BookingReminderSettingResource;
use App\Models\Booking;
use App\Models\BookingReminderSetting;
use App\Services\BookingReminderService;
use Illuminate\Http\JsonResponse;

/**
 * Jarak waktu pengingat sebelum jadwal, satu setelan untuk seluruh klinik.
 */
class BookingReminderSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $setting = BookingReminderSetting::query()->with('template:id,name', 'confirmationTemplate:id,name')->first();

        return response()->json([
            // Belum diatur bukan keadaan galat: formulirnya butuh bentuk yang
            // sama supaya tidak perlu menebak di sisi frontend.
            'data' => $setting !== null
                ? new BookingReminderSettingResource($setting)
                : [
                    'is_active' => false,
                    'offset_minutes' => 60,
                    'message_template_id' => null,
                    'template_name' => null,
                    'confirmation_template_id' => null,
                    'confirmation_template_name' => null,
                ],
            'meta' => [],
        ]);
    }

    public function update(BookingReminderSettingRequest $request, BookingReminderService $reminders): JsonResponse
    {
        $this->authorize('create', Booking::class);

        $setting = $reminders->save($request->validated());

        return response()->json([
            'data' => new BookingReminderSettingResource($setting),
            'meta' => ['message' => __('booking.reminder_saved')],
        ]);
    }
}
