<?php

namespace App\Actions\Booking;

use App\Actions\LogAuditAction;
use App\Enums\BookingReminderStatus;
use App\Jobs\SendBookingReminderJob;
use App\Models\Booking;
use App\Models\BookingReminder;
use App\Models\BookingReminderSetting;

/**
 * Jadwalkan pengingat untuk satu booking.
 *
 * Dua saklar harus sama-sama menyala: setelan klinik dan penanda per booking.
 * Yang pertama menentukan klinik memakai fitur ini sama sekali, yang kedua
 * menentukan jadwal ini pantas diingatkan — kontrol singkat atau pasien yang
 * memang sudah di tempat tidak perlu dikirimi apa-apa.
 *
 * Barisnya di-upsert, bukan ditambah. Menjadwalkan ulang berarti menimpa yang
 * lama, dan job lama yang terlanjur antre akan mendapati waktunya sudah tidak
 * cocok lalu berhenti sendiri.
 */
class ScheduleBookingReminderAction
{
    public function __construct(private readonly LogAuditAction $audit) {}

    public function handle(Booking $booking): ?BookingReminder
    {
        $setting = BookingReminderSetting::query()->first();

        if ($setting === null || ! $setting->is_active || ! $booking->remind_booking) {
            return null;
        }

        if ($booking->start_at === null) {
            return null;
        }

        $remindAt = $booking->start_at->copy()->subMinutes($setting->offset_minutes);

        // Booking yang didaftarkan mepet jadwal tetap diingatkan, sekarang
        // juga. Terlambat sedikit masih berguna; tidak dikirim sama sekali
        // membuat toggle-nya berbohong ke admin yang sudah menyalakannya.
        $delay = $remindAt->isPast() ? now() : $remindAt;

        $reminder = BookingReminder::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'remind_at' => $delay,
                'reminder_offset_minutes' => $setting->offset_minutes,
                'status' => BookingReminderStatus::Pending,
                'sent_at' => null,
            ],
        );

        SendBookingReminderJob::dispatch($booking->tenant_id, $reminder->id, $delay->toIso8601String())
            ->delay($delay);

        $booking->loadMissing('patient', 'service');

        $this->audit->handle(
            action: 'booking_reminder.scheduled',
            subject: $reminder,
            context: ['attributes' => $reminder->getAttributes(), 'booking_id' => $booking->id],
            description: 'Menjadwalkan pengingat booking '.($booking->service?->name ?? 'layanan')
                .' untuk '.($booking->patient?->name ?? 'pasien').' pada '
                .$booking->start_at->format('d/m/Y H:i').', dikirim pukul '
                .$delay->format('d/m/Y H:i').'.',
        );

        return $reminder;
    }
}
