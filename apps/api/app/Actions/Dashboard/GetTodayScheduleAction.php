<?php

namespace App\Actions\Dashboard;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Support\Carbon;

/**
 * Jadwal hari ini, urut jam. Yang dibatalkan tidak ikut — dasbor dipakai
 * untuk menyiapkan hari, bukan menelusuri riwayat.
 */
class GetTodayScheduleAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(): array
    {
        $today = Carbon::today();

        return Booking::query()
            ->with(['patient', 'service', 'assignee'])
            ->where('status', '!=', BookingStatus::Cancelled)
            ->whereBetween('start_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->orderBy('start_at')
            ->get()
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'patient_name' => $booking->patient?->name,
                'service_name' => $booking->service?->name,
                'assignee_name' => $booking->assignee?->name,
                'start_at' => $booking->start_at?->toIso8601String(),
                'end_at' => $booking->end_at?->toIso8601String(),
                'status' => $booking->status?->value,
                'status_label' => $booking->status?->label(),
            ])
            ->all();
    }
}
