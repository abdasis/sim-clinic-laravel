<?php

namespace App\Actions\Booking;

use App\Actions\LogAuditAction;
use App\Enums\BookingStatus;
use App\Models\Booking;

/**
 * Pindahkan status booking mengikuti alur yang diizinkan, mis. menunggu →
 * dikonfirmasi → selesai. Lompatan yang tidak masuk akal ditolak.
 */
class ChangeBookingStatusAction
{
    public function handle(Booking $booking, BookingStatus $target): Booking
    {
        if (! $booking->status->canTransitionTo($target)) {
            abort(422, __('clinic.invalid_transition'));
        }

        $old = $booking->status;

        $booking->update([
            'status' => $target,
            'status_changed_at' => now(),
        ]);

        $booking->load('patient', 'service', 'assignee');

        app(LogAuditAction::class)->handle(
            'booking.status_changed',
            $booking,
            auth()->user(),
            ['old' => ['status' => $old->value], 'new' => ['status' => $target->value]],
            'Status booking '.($booking->patient?->name ?? 'pasien').' diubah dari '
                .$old->label().' ke '.$target->label().'.',
        );

        return $booking;
    }
}
