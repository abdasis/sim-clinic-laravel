<?php

namespace App\Services;

use App\Actions\Booking\CancelBookingReminderAction;
use App\Actions\Booking\ChangeBookingStatusAction;
use App\Actions\Booking\CreateBookingAction;
use App\Actions\Booking\DeleteBookingAction;
use App\Actions\Booking\ScheduleBookingReminderAction;
use App\Actions\Booking\UpdateBookingAction;
use App\Enums\BookingStatus;
use App\Jobs\SendBookingConfirmationJob;
use App\Models\Booking;

/**
 * Use case penjadwalan booking. Bentrok jadwal hanya diperingatkan, tidak
 * memblokir, karena klinik kerap sengaja menumpuk jadwal singkat.
 *
 * Pengingat mengikuti bookingnya. Setiap perubahan yang membuat jadwal lama
 * tidak berlaku lagi — waktu digeser, status berubah, penandanya dimatikan —
 * membatalkan pengingat lama dulu, baru menjadwalkan yang baru bila memang
 * masih perlu.
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

        app(ScheduleBookingReminderAction::class)->handle($booking);

        return [$booking, app(BookingOverlapService::class)->detect($booking)];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0:Booking, 1:array<int, mixed>}
     */
    public function update(Booking $booking, array $attributes): array
    {
        $booking = app(UpdateBookingAction::class)->handle($booking, $attributes);

        // Dibatalkan tanpa syarat lalu dijadwalkan ulang, bukan dibandingkan
        // dulu apakah waktunya berubah: setelan offset klinik bisa ikut
        // bergeser di antara dua penyuntingan, jadi "waktu mulainya sama"
        // tidak menjamin waktu kirimnya juga sama.
        app(CancelBookingReminderAction::class)->handle($booking, 'jadwal booking diperbarui');
        app(ScheduleBookingReminderAction::class)->handle($booking);

        return [$booking, app(BookingOverlapService::class)->detect($booking)];
    }

    public function changeStatus(Booking $booking, string $status): Booking
    {
        $target = BookingStatus::from($status);
        $booking = app(ChangeBookingStatusAction::class)->handle($booking, $target);

        // Kunjungan yang sudah selesai atau dibatalkan tidak perlu diingatkan.
        if (in_array($target, [BookingStatus::Done, BookingStatus::Cancelled], true)) {
            app(CancelBookingReminderAction::class)->handle($booking, 'booking '.$target->label());
        }

        // Pasien menunggu kepastian; justru di detik inilah jadwalnya benar-
        // benar pasti. Lewat antrian supaya staf yang menekan konfirmasi
        // tidak menunggu gateway, dan gateway yang mati tidak membatalkan
        // konfirmasinya — statusnya sudah tersimpan di baris di atas.
        if ($target === BookingStatus::Confirmed) {
            SendBookingConfirmationJob::dispatch($booking->tenant_id, $booking->id);
        }

        return $booking;
    }

    public function delete(Booking $booking): void
    {
        app(CancelBookingReminderAction::class)->handle($booking, 'booking dihapus');

        app(DeleteBookingAction::class)->handle($booking);
    }
}
