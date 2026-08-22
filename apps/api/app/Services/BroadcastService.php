<?php

namespace App\Services;

use App\Actions\Broadcast\CreateBroadcastAction;
use App\Actions\Broadcast\DeleteBroadcastAction;
use App\Actions\Broadcast\RequeueFailedRecipientsAction;
use App\Actions\Broadcast\SaveAutoReminderSettingAction;
use App\Actions\Broadcast\UpdateRecipientStatusAction;
use App\Actions\LogAuditAction;
use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\BroadcastReminderSetting;
use App\Support\WahaClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orkestrasi broadcast WhatsApp: snapshot penerima, penandaan manual, dan
 * pengiriman lewat antrian dengan jeda antar pesan.
 */
class BroadcastService
{
    /** Jarak detik antar pesan agar tidak menyembur dan memicu blokir spam. */
    private const SECONDS_BETWEEN_MESSAGES = 5;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Broadcast
    {
        return DB::transaction(fn () => app(CreateBroadcastAction::class)->handle($data));
    }

    public function delete(Broadcast $broadcast): void
    {
        DB::transaction(fn () => app(DeleteBroadcastAction::class)->handle($broadcast));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveAutoReminderSetting(array $data): BroadcastReminderSetting
    {
        return DB::transaction(fn () => app(SaveAutoReminderSettingAction::class)->handle($data));
    }

    public function markRecipient(BroadcastRecipient $recipient, BroadcastRecipientStatus $status): BroadcastRecipient
    {
        return app(UpdateRecipientStatusAction::class)->handle($recipient, $status);
    }

    /**
     * Antrekan semua penerima menunggu, satu job per pesan dengan jeda
     * berjenjang — HTTP request kembali seketika, worker yang mengirim.
     *
     * @return array{queued: int}
     */
    public function queueSend(Broadcast $broadcast): array
    {
        $client = app(WahaClient::class);

        if ($client === null) {
            abort(422, __('broadcast.waha_not_ready'));
        }

        $this->guardConnected($client);

        // Yang gagal ikut diantrekan lagi: menekan kirim setelah sesi pulih
        // memang bermaksud mengulang yang tidak sampai, dan tanpa ini
        // campaign yang seluruhnya gagal jadi jalan buntu — satu-satunya
        // jalan keluar menandai ulang penerimanya satu per satu.
        $pendingIds = $broadcast->recipients()
            ->whereIn('status', [BroadcastRecipientStatus::Pending, BroadcastRecipientStatus::Failed])
            ->orderBy('id')
            ->pluck('id');

        app(RequeueFailedRecipientsAction::class)->handle($broadcast);

        $broadcast->update(['status' => BroadcastStatus::Sending]);

        foreach ($pendingIds as $index => $recipientId) {
            SendBroadcastRecipientJob::dispatch($recipientId, $broadcast->tenant_id)
                ->delay(now()->addSeconds($index * self::SECONDS_BETWEEN_MESSAGES));
        }

        app(LogAuditAction::class)->handle(
            'broadcast.queued',
            $broadcast,
            Auth::user(),
            ['new' => ['queued' => $pendingIds->count()]],
            'Mengantrekan broadcast '.$broadcast->title.' ('.$pendingIds->count().' pesan).',
        );

        return ['queued' => $pendingIds->count()];
    }

    /**
     * Tolak pengiriman saat WhatsApp klinik tidak tersambung.
     *
     * Pengaturan yang terisi hanya berarti aplikasi tahu ke mana harus
     * menembak — bukan bahwa nomornya masih tertaut. Sesi yang terputus
     * membuat gateway menolak tiap pesan satu per satu sampai seluruh
     * campaign hangus, dan admin baru tahu setelah semuanya habis.
     */
    private function guardConnected(WahaClient $client): void
    {
        try {
            $connected = $client->isConnected();
        } catch (Throwable $e) {
            Log::error('Gagal memeriksa koneksi WhatsApp sebelum broadcast.', ['exception' => $e]);

            abort(422, __('broadcast.waha_check_failed'));
        }

        abort_unless($connected, 422, __('broadcast.waha_not_connected'));
    }

    public function changeStatus(Broadcast $broadcast, BroadcastStatus $status): Broadcast
    {
        $old = $broadcast->status;

        // Resume = antrekan ulang sisa pending; job lama yang melihat status
        // jeda sudah gugur tanpa mengirim.
        if ($status === BroadcastStatus::Sending) {
            $this->queueSend($broadcast);

            return $broadcast->refresh();
        }

        $broadcast->update(['status' => $status]);

        app(LogAuditAction::class)->handle(
            'broadcast.status_changed',
            $broadcast,
            Auth::user(),
            ['old' => ['status' => $old], 'new' => ['status' => $status]],
            'Mengubah status broadcast '.$broadcast->title.' menjadi '.$status->label().'.',
        );

        return $broadcast;
    }

    /**
     * Kirim pesan uji ke satu nomor — memastikan koneksi hidup sebelum
     * ratusan pasien dikirimi.
     */
    public function sendTest(string $phone, string $message): void
    {
        $client = app(WahaClient::class);

        if ($client === null) {
            abort(422, __('broadcast.waha_not_ready'));
        }

        $client->send($phone, $message);
    }
}
