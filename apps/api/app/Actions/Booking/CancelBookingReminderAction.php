<?php

namespace App\Actions\Booking;

use App\Actions\LogAuditAction;
use App\Enums\BookingReminderStatus;
use App\Models\Booking;
use App\Models\BookingReminder;

/**
 * Batalkan pengingat yang belum terkirim.
 *
 * Job tertundanya tidak ditarik dari antrian — antrian database tidak
 * menyediakan cara itu. Yang diubah statusnya, dan job yang kemudian bangun
 * akan membacanya lalu berhenti tanpa mengirim apa pun.
 *
 * Yang sudah terkirim tidak disentuh: pesannya sudah sampai ke pasien, dan
 * menandainya "dibatalkan" hanya membuat catatan berbohong.
 */
class CancelBookingReminderAction
{
    public function __construct(private readonly LogAuditAction $audit) {}

    public function handle(Booking $booking, string $reason): ?BookingReminder
    {
        $reminder = BookingReminder::query()
            ->where('booking_id', $booking->id)
            ->where('status', BookingReminderStatus::Pending)
            ->first();

        if ($reminder === null) {
            return null;
        }

        $reminder->update(['status' => BookingReminderStatus::Cancelled]);

        $booking->loadMissing('patient');

        $this->audit->handle(
            action: 'booking_reminder.cancelled',
            subject: $reminder,
            context: ['booking_id' => $booking->id, 'reason' => $reason],
            description: 'Membatalkan pengingat booking untuk '
                .($booking->patient?->name ?? 'pasien').': '.$reason.'.',
        );

        return $reminder;
    }
}
