<?php

namespace App\Jobs;

use App\Actions\LogAuditAction;
use App\Enums\BookingReminderStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingReminder;
use App\Models\BookingReminderSetting;
use App\Models\Tenant;
use App\Support\PhoneNumber;
use App\Support\WahaClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kirim satu pengingat jadwal ke pasien.
 *
 * Job tertunda tidak bisa ditarik kembali dari antrian, jadi yang menentukan
 * jadi-tidaknya pengiriman adalah baris pengingat saat job ini bangun — bukan
 * keadaan waktu ia dijadwalkan. Karena itu waktu yang dijanjikan ikut dibawa:
 * kalau bookingnya sudah dijadwalkan ulang, job lama menemukan waktunya tidak
 * lagi cocok dan berhenti tanpa mengirim apa pun.
 */
class SendBookingReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> detik tunggu antar percobaan */
    public array $backoff = [60, 300];

    private const DEFAULT_TEMPLATE = "Halo :pasien, kami mengingatkan jadwal Anda di *:klinik*.\n\n"
        ."*:layanan*\n:tanggal pukul :jam\nBersama :staf\n\n"
        .'Mohon datang sedikit lebih awal ya. Kalau ada perubahan, balas chat ini saja.';

    public function __construct(
        public readonly int $tenantId,
        public readonly int $reminderId,
        /** Waktu yang dijanjikan saat dijadwalkan, penanda job ini masih berlaku. */
        public readonly string $scheduledFor,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        // Job tidak lewat middleware ResolveTenant, jadi tenant diikat manual
        // supaya TenantScope aktif sebelum kueri apa pun berjalan.
        app()->instance('tenant', $tenant);

        $reminder = BookingReminder::query()->with('booking.patient', 'booking.service', 'booking.assignee')->find($this->reminderId);

        if ($reminder === null || ! $this->stillValid($reminder)) {
            return;
        }

        $booking = $reminder->booking;
        $phone = PhoneNumber::normalize($booking?->patient?->whatsapp);

        if ($booking === null || $phone === null) {
            $this->markFailed($reminder, 'nomor pasien tidak valid');

            return;
        }

        $this->send($reminder, $phone, $this->compose($booking, $tenant->name));
    }

    /**
     * Masih pantas dikirim?
     *
     * Empat hal diperiksa sekaligus karena masing-masing punya cara sendiri
     * membuat pengingat jadi salah: sudah terkirim (retry), sudah dibatalkan,
     * sudah dijadwalkan ulang, atau bookingnya sudah selesai/batal.
     */
    private function stillValid(BookingReminder $reminder): bool
    {
        if ($reminder->status !== BookingReminderStatus::Pending) {
            return false;
        }

        // Toleransi semenit, bukan sama persis: sebagian driver membulatkan
        // detik saat menyimpan, dan selisih sekian detik bukan berarti
        // jadwalnya diubah.
        $promised = Carbon::parse($this->scheduledFor);

        if ($reminder->remind_at === null
            || $reminder->remind_at->diffInSeconds($promised, absolute: true) > 60) {
            Log::info('Pengingat booking dilewati: jadwalnya sudah diubah.', [
                'reminder_id' => $reminder->id,
                'dijanjikan' => $this->scheduledFor,
                'sekarang' => $reminder->remind_at?->toIso8601String(),
            ]);

            return false;
        }

        $booking = $reminder->booking;

        if ($booking === null || ! $booking->remind_booking) {
            return false;
        }

        return in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true);
    }

    private function compose(Booking $booking, string $clinicName): string
    {
        $template = BookingReminderSetting::query()->first()?->template?->body
            ?? self::DEFAULT_TEMPLATE;

        return strtr($template, [
            ':pasien' => $booking->patient?->name ?? 'Bapak/Ibu',
            ':klinik' => $clinicName,
            ':layanan' => $booking->service?->name ?? 'perawatan',
            ':staf' => $booking->assignee?->name ?? 'tim kami',
            ':tanggal' => $booking->start_at?->translatedFormat('l, d F Y') ?? '-',
            ':jam' => $booking->start_at?->format('H:i') ?? '-',
        ]);
    }

    private function send(BookingReminder $reminder, string $phone, string $message): void
    {
        $client = app(WahaClient::class);

        if (! $client instanceof WahaClient) {
            $this->markFailed($reminder, 'gateway WhatsApp belum siap');

            return;
        }

        try {
            $client->send($phone, $message);
        } catch (Throwable $e) {
            Log::error('Pengingat booking gagal dikirim.', [
                'exception' => $e,
                'reminder_id' => $reminder->id,
                'tenant_id' => $this->tenantId,
            ]);

            // Dilempar ulang supaya percobaan berikutnya jalan; status baru
            // ditandai gagal setelah percobaan terakhir habis, lewat failed().
            throw $e;
        }

        $reminder->update(['status' => BookingReminderStatus::Sent, 'sent_at' => now()]);

        app(LogAuditAction::class)->handle(
            action: 'booking_reminder.sent',
            subject: $reminder,
            context: ['booking_id' => $reminder->booking_id, 'phone' => $phone],
            description: 'Mengirim pengingat jadwal ke '
                .($reminder->booking?->patient?->name ?? 'pasien').' lewat WhatsApp.',
        );
    }

    /** Kegagalan yang tidak layak dicoba ulang — datanya memang tidak cukup. */
    private function markFailed(BookingReminder $reminder, string $reason): void
    {
        Log::error('Pengingat booking tidak bisa dikirim.', [
            'reminder_id' => $reminder->id,
            'tenant_id' => $this->tenantId,
            'alasan' => $reason,
        ]);

        $reminder->update(['status' => BookingReminderStatus::Failed]);
    }

    /** Dipanggil setelah percobaan terakhir habis. */
    public function failed(?Throwable $e): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        app()->instance('tenant', $tenant);

        BookingReminder::query()
            ->where('id', $this->reminderId)
            ->where('status', BookingReminderStatus::Pending)
            ->update(['status' => BookingReminderStatus::Failed]);
    }
}
