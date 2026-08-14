<?php

namespace App\Services;

use App\Actions\Booking\ChangeBookingStatusAction;
use App\Actions\Booking\CreateBookingAction;
use App\Actions\Booking\DeleteBookingAction;
use App\Actions\Booking\UpdateBookingAction;
use App\Enums\BookingStatus;
use App\Models\Booking;

/**
 * Use case penjadwalan booking. Bentrok jadwal hanya diperingatkan, tidak
 * memblokir, karena klinik kerap sengaja menumpuk jadwal singkat.
 */
class BookingService
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0:Booking, 1:array<int, mixed>}
     */
    public function create(array $attributes): array
    {
        $booking = app(CreateBookingAction::class)->handle($attributes);

        return [$booking, app(BookingOverlapService::class)->detect($booking)];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0:Booking, 1:array<int, mixed>}
     */
    public function update(Booking $booking, array $attributes): array
    {
        $booking = app(UpdateBookingAction::class)->handle($booking, $attributes);

        return [$booking, app(BookingOverlapService::class)->detect($booking)];
    }

    public function changeStatus(Booking $booking, string $status): Booking
    {
        return app(ChangeBookingStatusAction::class)->handle($booking, BookingStatus::from($status));
    }

    public function delete(Booking $booking): void
    {
        app(DeleteBookingAction::class)->handle($booking);
    }
}
