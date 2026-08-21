<?php

namespace App\Enums;

enum BookingReminderStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('booking.reminder_status.'.$this->value);
    }
}
