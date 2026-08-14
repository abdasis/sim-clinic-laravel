<?php

namespace App\Actions\Booking;

use App\Actions\LogAuditAction;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hapus booking yang batal dibuat. Jejaknya tetap tercatat di activity log.
 */
class DeleteBookingAction
{
    public function handle(Booking $booking): void
    {
        $snapshot = $booking->getAttributes();
        $patientName = $booking->patient?->name ?? 'pasien';

        try {
            $booking->delete();
        } catch (Throwable $e) {
            Log::error('Gagal menghapus booking.', [
                'exception' => $e,
                'booking_id' => $booking->id,
                'tenant_id' => $booking->tenant_id,
            ]);

            throw $e;
        }

        app(LogAuditAction::class)->handle(
            'booking.deleted',
            null,
            auth()->user(),
            ['attributes' => $snapshot],
            'Menghapus booking '.$patientName.'.',
        );
    }
}
