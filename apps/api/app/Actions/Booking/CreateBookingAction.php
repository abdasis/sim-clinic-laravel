<?php

namespace App\Actions\Booking;

use App\Actions\LogAuditAction;
use App\Enums\BookingStatus;
use App\Models\Booking;

/**
 * Buat jadwal booking baru; selalu mulai dari status menunggu konfirmasi.
 */
class CreateBookingAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Booking
    {
        $booking = Booking::create([
            ...$attributes,
            'status' => BookingStatus::Pending,
        ]);

        $booking->load('patient', 'service', 'assignee');

        app(LogAuditAction::class)->handle(
            'booking.created',
            $booking,
            auth()->user(),
            ['attributes' => $booking->getAttributes()],
            'Membuat booking '.($booking->service?->name ?? 'layanan').' untuk '
                .($booking->patient?->name ?? 'pasien').' pada '
                .$booking->start_at?->format('d/m/Y H:i').'.',
        );

        return $booking;
    }
}
