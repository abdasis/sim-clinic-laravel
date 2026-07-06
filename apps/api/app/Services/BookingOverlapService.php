<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;

/**
 * Deteksi bentrok jadwal untuk staf yang sama (FR-035, R8).
 * Non-blocking: hasil dipakai sebagai warning, bukan error.
 */
class BookingOverlapService
{
    /**
     * @return array<int, array{booking_id: int, patient_name: ?string, start_at: ?string, end_at: ?string}>
     */
    public function detect(Booking $booking): array
    {
        $overlaps = Booking::query()
            ->with('patient')
            ->where('assignee_id', $booking->assignee_id)
            ->where('id', '!=', $booking->id)
            ->where('status', '!=', BookingStatus::Cancelled)
            ->where('start_at', '<', $booking->end_at)
            ->where('end_at', '>', $booking->start_at)
            ->orderBy('start_at')
            ->get();

        return $overlaps->map(fn (Booking $other): array => [
            'booking_id' => $other->id,
            'patient_name' => $other->patient?->name,
            'start_at' => $other->start_at?->toIso8601String(),
            'end_at' => $other->end_at?->toIso8601String(),
        ])->all();
    }
}
