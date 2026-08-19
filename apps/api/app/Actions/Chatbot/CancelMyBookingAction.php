<?php

namespace App\Actions\Chatbot;

use App\Actions\Booking\ChangeBookingStatusAction;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Patient;

/**
 * Batalkan booking atas permintaan pasien lewat chat.
 *
 * Penjaga kepemilikannya yang paling penting di sini: nomor id booking mudah
 * ditebak, dan tanpa pemeriksaan itu siapa pun yang chat bisa membatalkan
 * jadwal orang lain hanya dengan menyebut angka.
 */
class CancelMyBookingAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Patient $patient, int $bookingId): array
    {
        $booking = Booking::query()->with('service:id,name')->find($bookingId);

        if ($booking === null) {
            return $this->refuse(__('chatbot.booking_not_found'));
        }

        if ($booking->patient_id !== $patient->id) {
            // Alasannya sengaja sama dengan "tidak ditemukan" dari sudut
            // pandang pasien; menyebut "itu milik orang lain" mengakui bahwa
            // bookingnya ada, dan itu sudah membocorkan sesuatu.
            return $this->refuse(__('chatbot.booking_not_yours'));
        }

        if (! $booking->status->canTransitionTo(BookingStatus::Cancelled)) {
            return $this->refuse(__('chatbot.booking_not_cancellable'));
        }

        // Aksi ini sudah mencatat audit log perubahan statusnya sendiri.
        app(ChangeBookingStatusAction::class)->handle($booking, BookingStatus::Cancelled);

        return [
            'cancelled' => true,
            'booking_id' => $booking->id,
            'service' => $booking->service?->name,
            'start_at' => $booking->start_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array{cancelled: false, reason: string}
     */
    private function refuse(string $reason): array
    {
        return ['cancelled' => false, 'reason' => $reason];
    }
}
