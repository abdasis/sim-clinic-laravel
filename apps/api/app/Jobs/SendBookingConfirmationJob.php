<?php

namespace App\Jobs;

use App\Actions\LogAuditAction;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingReminderSetting;
use App\Models\Tenant;
use App\Support\BookingMessage;
use App\Support\ClinicIdentity;
use App\Support\PhoneNumber;
use App\Support\WahaClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kabari pasien begitu kliniknya menekan konfirmasi di dasbor.
 *
 * Sebelumnya konfirmasi hanya mengubah status di layar staf; pasien yang
 * menunggu kepastian tidak menerima apa pun dan kerap menanyakannya lagi
 * lewat chat. Padahal justru di detik itulah jadwalnya benar-benar pasti.
 *
 * Dikerjakan lewat antrian, bukan di tengah request: staf yang menekan
 * konfirmasi tidak boleh menunggu gateway WhatsApp, dan gateway yang sedang
 * mati tidak boleh membatalkan konfirmasinya. Statusnya sudah tersimpan
 * sebelum job ini bahkan mulai.
 */
class SendBookingConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> detik tunggu antar percobaan */
    public array $backoff = [60, 300];

    private const DEFAULT_TEMPLATE = "Halo :pasien, jadwal Anda di *:klinik* sudah kami *konfirmasi*. 🎉\n\n"
        ."*:layanan*\n:tanggal pukul :jam\nBersama :staf\n\n"
        .'Sampai jumpa ya. Kalau ada perubahan, balas chat ini saja.';

    public function __construct(
        public readonly int $tenantId,
        public readonly int $bookingId,
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

        $booking = Booking::query()
            ->with('patient', 'service', 'assignee')
            ->find($this->bookingId);

        // Konfirmasi bisa dicabut sebelum job ini bangun. Mengabari pasien
        // bahwa jadwalnya pasti, padahal barusan dibatalkan, lebih buruk
        // daripada tidak mengabari sama sekali.
        if ($booking === null || $booking->status !== BookingStatus::Confirmed) {
            return;
        }

        $phone = PhoneNumber::normalize($booking->patient?->whatsapp);

        if ($phone === null) {
            Log::info('Konfirmasi booking tidak dikirim: nomor pasien tidak ada atau tidak valid.', [
                'booking_id' => $booking->id,
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $client = app(WahaClient::class);

        if (! $client instanceof WahaClient) {
            Log::warning('Konfirmasi booking tidak dikirim: gateway WhatsApp belum siap.', [
                'booking_id' => $booking->id,
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        try {
            $client->send($phone, $this->compose($booking, ClinicIdentity::displayName($tenant)));
        } catch (Throwable $e) {
            Log::error('Konfirmasi booking gagal dikirim.', [
                'exception' => $e,
                'booking_id' => $booking->id,
                'tenant_id' => $this->tenantId,
            ]);

            // Dilempar ulang supaya antrian mengulanginya dengan backoff.
            throw $e;
        }

        app(LogAuditAction::class)->handle(
            action: 'booking.confirmation_sent',
            subject: $booking,
            context: ['booking_id' => $booking->id, 'phone' => $phone],
            description: 'Mengabari '.($booking->patient?->name ?? 'pasien')
                .' lewat WhatsApp bahwa jadwalnya sudah dikonfirmasi klinik.',
        );
    }

    /**
     * Teks racikan klinik menang atas teks bawaan; yang belum memilih tetap
     * dapat kalimat yang layak, bukan pesan kosong.
     */
    private function compose(Booking $booking, string $clinicName): string
    {
        $template = BookingReminderSetting::query()->first()?->confirmationTemplate?->body
            ?? self::DEFAULT_TEMPLATE;

        return BookingMessage::render($template, $booking, $clinicName);
    }
}
