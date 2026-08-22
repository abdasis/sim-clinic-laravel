<?php

namespace App\Actions\Chatbot;

use App\Enums\BookingStatus;
use App\Enums\ClinicRole;
use App\Enums\ServiceStatus;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\ChatbotSetting;
use App\Models\CompanyProfileSetting;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Periksa apakah satu slot waktu benar-benar bisa dipakai.
 *
 * Ini yang membedakan asisten booking dari mesin pencatat: tanpa pemeriksaan
 * di depan, chatbot menawarkan jam yang ternyata penuh atau hari klinik tutup,
 * lalu bentroknya baru ketahuan setelah pasien merasa jadwalnya sudah pasti.
 *
 * Semua penolakan pulang sebagai alasan yang bisa langsung dibacakan, bukan
 * exception — yang memanggil adalah AI, dan alasan yang terbaca itulah yang
 * sampai ke pasien.
 */
class CheckAvailabilityAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(int $serviceId, ?int $assigneeId, string $startAt): array
    {
        $start = $this->parse($startAt);

        if ($start === null) {
            return $this->unavailable(__('chatbot.availability_time_invalid'));
        }

        if ($start->isPast()) {
            return $this->unavailable(__('chatbot.availability_time_past'));
        }

        $service = Service::query()->where('status', ServiceStatus::Active)->find($serviceId);

        if ($service === null) {
            return $this->unavailable(__('chatbot.booking_service_unknown'));
        }

        // Penolakan ini dulu hanya ada di create_booking, jadi chatbot
        // menawarkan jam, pasien menyetujuinya, lalu baru ditolak di langkah
        // terakhir. Menolak di sini membuat jadwal yang tidak bisa dipenuhi
        // tidak pernah sempat ditawarkan.
        $setting = ChatbotSetting::query()->first();

        if ($setting !== null && ! $setting->allowsBooking($service->id)) {
            return $this->unavailable(
                __('chatbot.booking_service_not_bookable', ['service' => $service->name]),
                $start,
            );
        }

        $end = $start->copy()->addMinutes((int) ($service->duration_minutes ?: 60));

        $profile = CompanyProfileSetting::query()->first();

        if ($profile !== null && ! $profile->isOpenAt($start)) {
            return $this->unavailable(__('chatbot.availability_clinic_closed'), $start);
        }

        return $assigneeId === null
            ? $this->anyStaff($start, $end)
            : $this->oneStaff($assigneeId, $start, $end);
    }

    /** Pasien menyebut orangnya: cukup periksa jadwal orang itu. */
    private function oneStaff(int $assigneeId, Carbon $start, Carbon $end): array
    {
        $assignee = $this->staffQuery()->find($assigneeId);

        if ($assignee === null) {
            return $this->unavailable(__('chatbot.booking_staff_unknown'), $start);
        }

        $conflicts = $this->conflicts([$assigneeId], $start, $end);

        return [
            'available' => $conflicts->isEmpty(),
            // Nama orang yang diperiksa ikut pulang supaya identitasnya tidak
            // hilang di tengah percakapan: pasien menyebut satu nama, dan
            // jawaban tanpa nama membuat AI mudah menggantinya diam-diam.
            'staff_id' => $assignee->id,
            'staff_name' => $assignee->name,
            'start_at' => $start->format('Y-m-d H:i'),
            'reason' => $conflicts->isEmpty() ? null : __('chatbot.availability_staff_busy'),
            'conflicts' => $conflicts->values()->all(),
        ];
    }

    /** Pasien belum menyebut siapa: tawarkan yang jadwalnya memang kosong. */
    private function anyStaff(Carbon $start, Carbon $end): array
    {
        $staff = $this->staffQuery()->get(['id', 'name']);
        $busy = $this->conflicts($staff->pluck('id')->all(), $start, $end)
            ->pluck('assignee_id')
            ->unique();

        $free = $staff->reject(fn (User $user) => $busy->contains($user->id))
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->values();

        return [
            'available' => $free->isNotEmpty(),
            'start_at' => $start->format('Y-m-d H:i'),
            'reason' => $free->isNotEmpty() ? null : __('chatbot.availability_no_staff'),
            'available_staff' => $free->all(),
            'conflicts' => [],
        ];
    }

    /**
     * Booking yang beririsan dengan rentang ini.
     *
     * ponytail: kondisinya disalin dari BookingOverlapService::detect karena di
     * sini belum ada booking tersimpan yang bisa diserahkan ke sana. Kalau
     * aturan bentroknya berubah, dua tempat ini harus ikut berubah bersamaan;
     * satukan dengan mengeluarkan kondisinya jadi query scope pada Booking.
     *
     * @param  array<int, int>  $assigneeIds
     * @return Collection<int, array<string, mixed>>
     */
    private function conflicts(array $assigneeIds, Carbon $start, Carbon $end): Collection
    {
        if ($assigneeIds === []) {
            return collect();
        }

        return Booking::query()
            ->with('assignee:id,name')
            ->whereIn('assignee_id', $assigneeIds)
            ->where('status', '!=', BookingStatus::Cancelled)
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->orderBy('start_at')
            ->get()
            ->map(fn (Booking $booking): array => [
                'assignee_id' => $booking->assignee_id,
                'staff_name' => $booking->assignee?->name,
                'start_at' => $booking->start_at?->format('Y-m-d H:i'),
                'end_at' => $booking->end_at?->format('Y-m-d H:i'),
            ]);
    }

    private function staffQuery()
    {
        return User::query()
            ->whereIn('clinic_role', [ClinicRole::Doctor, ClinicRole::Therapist])
            ->where('status', UserStatus::Active)
            ->orderBy('name');
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(string $reason, ?Carbon $start = null): array
    {
        return [
            'available' => false,
            'start_at' => $start?->format('Y-m-d H:i'),
            'reason' => $reason,
            'conflicts' => [],
        ];
    }

    private function parse(string $startAt): ?Carbon
    {
        try {
            return Carbon::parse($startAt);
        } catch (Throwable $e) {
            Log::error('Waktu yang dicek chatbot tidak bisa dibaca.', [
                'exception' => $e,
                'start_at' => $startAt,
            ]);

            return null;
        }
    }
}
