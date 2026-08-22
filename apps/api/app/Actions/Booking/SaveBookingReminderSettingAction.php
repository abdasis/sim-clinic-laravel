<?php

namespace App\Actions\Booking;

use App\Actions\LogAuditAction;
use App\Models\BookingReminderSetting;

/**
 * Simpan setelan pengingat booking. Satu baris per klinik, jadi selalu upsert.
 */
class SaveBookingReminderSettingAction
{
    public function __construct(private readonly LogAuditAction $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): BookingReminderSetting
    {
        $setting = BookingReminderSetting::query()->first();
        $tracked = ['is_active', 'offset_minutes', 'message_template_id', 'confirmation_template_id'];
        $before = $setting?->only($tracked);

        if ($setting === null) {
            $setting = BookingReminderSetting::create($attributes);
        } else {
            $setting->update($attributes);
        }

        $setting->refresh();

        $this->audit->handle(
            action: $before === null ? 'booking_reminder_setting.created' : 'booking_reminder_setting.updated',
            subject: $setting,
            context: $before === null
                ? ['attributes' => $setting->getAttributes()]
                : ['old' => $before, 'new' => $setting->only($tracked)],
            description: $setting->is_active
                ? 'Menyalakan pengingat booking, dikirim '.$setting->offset_minutes.' menit sebelum jadwal.'
                : 'Mematikan pengingat booking.',
        );

        return $setting;
    }
}
