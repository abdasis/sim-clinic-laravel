<?php

namespace App\Actions\Chatbot;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Patient;

/**
 * Jadwal mendatang milik pasien yang sedang chat.
 *
 * Hanya yang belum lewat dan belum dibatalkan: yang ditanyakan pasien lewat
 * WhatsApp selalu "kapan saya datang", bukan riwayat kunjungannya.
 */
class ListPatientBookingsAction
{
    private const LIMIT = 10;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Patient $patient): array
    {
        return Booking::query()
            ->with(['service:id,name', 'assignee:id,name'])
            ->where('patient_id', $patient->id)
            ->where('status', '!=', BookingStatus::Cancelled)
            ->where('start_at', '>=', now())
            ->orderBy('start_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Booking $booking): array => [
                'booking_id' => $booking->id,
                'service_name' => $booking->service?->name,
                'staff_name' => $booking->assignee?->name,
                'start_at' => $booking->start_at?->format('Y-m-d H:i'),
                'end_at' => $booking->end_at?->format('Y-m-d H:i'),
                'status' => $booking->status->label(),
            ])
            ->all();
    }
}
