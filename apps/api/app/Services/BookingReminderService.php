<?php

namespace App\Services;

use App\Actions\Booking\SaveBookingReminderSettingAction;
use App\Models\BookingReminderSetting;

/**
 * Use case setelan pengingat booking. Dipisah dari BookingService yang
 * mengurus jadwalnya sendiri: menyimpan setelan tidak menyentuh booking mana
 * pun, dan sebaliknya.
 */
class BookingReminderService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(array $attributes): BookingReminderSetting
    {
        return app(SaveBookingReminderSettingAction::class)->handle($attributes);
    }
}
