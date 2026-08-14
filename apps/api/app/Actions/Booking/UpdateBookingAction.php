<?php

namespace App\Actions\Booking;

use App\Actions\LogAuditAction;
use App\Models\Booking;

/**
 * Perbarui detail booking (jadwal, layanan, penanggung jawab).
 */
class UpdateBookingAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Booking $booking, array $attributes): Booking
    {
        $old = $booking->only(array_keys($attributes));

        $booking->update($attributes);

        $booking->load('patient', 'service', 'assignee');

        app(LogAuditAction::class)->handle(
            'booking.updated',
            $booking,
            auth()->user(),
            ['old' => $old, 'new' => $booking->only(array_keys($attributes))],
            'Memperbarui booking '.($booking->patient?->name ?? 'pasien').'.',
        );

        return $booking;
    }
}
