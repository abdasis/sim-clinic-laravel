<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('clinic.booking_status.'.$this->value);
    }

    /**
     * Transisi status valid (FR-031). done tidak boleh cancelled.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Confirmed, self::Cancelled], true),
            self::Confirmed => in_array($target, [self::Done, self::Cancelled], true),
            self::Done, self::Cancelled => false,
        };
    }
}
