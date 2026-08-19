<?php

namespace App\Actions\Chatbot;

use App\Actions\Booking\CreateBookingAction;
use App\Actions\LogAuditAction;
use App\Enums\ClinicRole;
use App\Enums\ServiceStatus;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\ChatbotSetting;
use App\Models\CompanyProfileSetting;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Buat booking atas permintaan pasien lewat chat.
 *
 * Semua penolakan dikembalikan sebagai teks alasan, bukan exception: yang
 * memanggil adalah AI, dan alasan yang bisa dibacanya akan diteruskan ke
 * pasien sebagai kalimat — sementara exception hanya akan berakhir jadi
 * "maaf, ada kendala" yang tidak menjelaskan apa pun.
 *
 * Penjaganya sengaja ketat karena percakapan bukan formulir: tidak ada yang
 * mengawasi apa yang diketik pasien, dan AI bisa saja menebak id yang tidak
 * pernah disebut siapa pun.
 */
class CreateChatBookingAction
{
    /**
     * @return array{booking: ?Booking, error: ?string}
     */
    public function handle(Patient $patient, int $serviceId, int $assigneeId, string $startAt): array
    {
        $service = Service::query()->where('status', ServiceStatus::Active)->find($serviceId);

        if ($service === null) {
            return $this->reject(__('chatbot.booking_service_unknown'));
        }

        $setting = ChatbotSetting::query()->first();

        if ($setting !== null && ! $setting->allowsBooking($service->id)) {
            return $this->reject(__('chatbot.booking_service_not_bookable', ['service' => $service->name]));
        }

        $assignee = User::query()
            ->whereIn('clinic_role', [ClinicRole::Doctor, ClinicRole::Therapist])
            ->where('status', UserStatus::Active)
            ->find($assigneeId);

        if ($assignee === null) {
            return $this->reject(__('chatbot.booking_staff_unknown'));
        }

        $start = $this->parseStart($startAt);

        if ($start === null) {
            return $this->reject(__('chatbot.booking_time_invalid'));
        }

        if ($start->isPast()) {
            return $this->reject(__('chatbot.booking_time_past'));
        }

        // Penjaga terakhir. AI sudah disuruh memanggil check_availability
        // dulu, tapi "disuruh" bukan jaminan — dan jadwal di hari klinik
        // tutup berakhir sebagai pasien yang datang ke pintu terkunci.
        $profile = CompanyProfileSetting::query()->first();

        if ($profile !== null && ! $profile->isOpenAt($start)) {
            return $this->reject(__('chatbot.booking_outside_hours'));
        }

        return ['booking' => $this->create($patient, $service, $assignee, $start), 'error' => null];
    }

    private function create(Patient $patient, Service $service, User $assignee, Carbon $start): Booking
    {
        $booking = app(CreateBookingAction::class)->handle([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'assignee_id' => $assignee->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addMinutes((int) ($service->duration_minutes ?: 60)),
        ]);

        // CreateBookingAction sudah mencatat pembuatannya, tapi tidak tahu dari
        // mana asalnya. Baris ini yang membedakan booking hasil percakapan dari
        // booking yang diketik staf — pertanyaan pertama saat ada jadwal
        // mencurigakan selalu "ini siapa yang bikin?".
        app(LogAuditAction::class)->handle(
            'booking.created_via_chatbot',
            $booking,
            null,
            [
                'source' => 'chatbot',
                'attributes' => $booking->getAttributes(),
                'sender_phone' => $patient->whatsapp,
            ],
            'Membuat booking '.$service->name.' untuk '.$patient->name.' pada '
                .$start->format('d/m/Y H:i').' melalui chatbot AI.',
        );

        return $booking;
    }

    /**
     * @return array{booking: null, error: string}
     */
    private function reject(string $reason): array
    {
        return ['booking' => null, 'error' => $reason];
    }

    private function parseStart(string $startAt): ?Carbon
    {
        try {
            return Carbon::parse($startAt);
        } catch (Throwable $e) {
            Log::error('Waktu booking dari chatbot tidak bisa dibaca.', [
                'exception' => $e,
                'start_at' => $startAt,
            ]);

            return null;
        }
    }
}
