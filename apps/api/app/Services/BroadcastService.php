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
use App\Support\CloudApiClient;
use App\Support\DailyMessageQuota;
use App\Support\TemplateVariables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Orkestrasi broadcast WhatsApp: snapshot penerima, penandaan manual, dan
 * pengiriman lewat antrian dengan jeda antar pesan.
 */
class BroadcastService
{
    /**
     * Rentang detik antar pesan, diacak per kiriman.
     *
     * Jaraknya bukan cuma soal tidak menyembur. Jeda yang persis sama untuk
     * ratusan pesan berturut-turut adalah irama yang tidak pernah dihasilkan
     * orang mengetik, dan justru keteraturan itu yang paling gampang dibaca
     * sebagai bot — walau isi pesannya sah. Rata-ratanya sengaja tetap 5
     * detik supaya durasi blast tidak berubah.
     *
     * ponytail: rentang selebar ini cukup untuk ratusan pesan sekali blast.
     * Untuk daftar yang jauh lebih besar (di atas seribu), sebarannya perlu
     * lebih lebar dan sebaiknya berkelompok — jeda panjang sesekali di antara
     * rentetan pendek, meniru orang yang berhenti sejenak — bukan sekadar
     * acak seragam.
     */
    private const MIN_SECONDS_BETWEEN_MESSAGES = 3;

    private const MAX_SECONDS_BETWEEN_MESSAGES = 7;

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
    public function queueSend(Broadcast $broadcast, bool $automatic = false): array
    {
        // Cloud API yang belum disetel atau template yang belum dipilih
        // membuat seluruh antrean gugur satu per satu di worker. Ditolak di
        // depan supaya admin tahu sebelum menekan kirim.
        if (! app(CloudApiClient::class) instanceof CloudApiClient) {
            abort(422, __('broadcast.cloud_not_ready'));
        }

        abort_if($broadcast->template === null, 422, __('broadcast.template_required'));

        // Jatah yang sudah habis ditolak di depan, bukan setelah ratusan job
        // diantrekan untuk gugur satu per satu. Sisa jatah yang cuma menutup
        // sebagian tetap dijalankan — yang tidak kebagian hari ini dijeda oleh
        // worker begitu jatahnya menyentuh batas, lalu dilanjutkan besok.
        $quota = DailyMessageQuota::today();

        abort_if(
            $quota->isSpent(),
            422,
            __('broadcast.daily_limit_reached', ['limit' => $quota->limit]),
        );

        // Yang gagal ikut diantrekan lagi: menekan kirim setelah gateway
        // pulih memang bermaksud mengulang yang tidak sampai, dan tanpa ini
        // campaign yang seluruhnya gagal jadi jalan buntu — satu-satunya
        // jalan keluar menandai ulang penerimanya satu per satu.
        $pendingIds = $broadcast->recipients()
            ->whereIn('status', [BroadcastRecipientStatus::Pending, BroadcastRecipientStatus::Failed])
            ->orderBy('id')
            ->pluck('id');

        app(RequeueFailedRecipientsAction::class)->handle($broadcast);

        // Alasan jeda sebelumnya ikut dihapus: campaign yang jalan lagi
        // tidak boleh masih menyandang keterangan berhenti yang lama.
        //
        // Hitungan lanjut-otomatis hanya direset oleh orang. Kalau penjadwal
        // ikut meresetnya, gateway yang putus-nyambung memicu jeda-lanjut
        // tanpa akhir dan tidak ada yang pernah dimintai perhatian.
        $broadcast->update([
            'status' => BroadcastStatus::Sending,
            'paused_reason' => null,
            'resume_after' => null,
            'auto_resumes' => $automatic ? $broadcast->auto_resumes + 1 : 0,
        ]);

        // Jarak ditumpuk berjalan, bukan dikali nomor urut: mengalikan indeks
        // dengan angka acak bisa membuat pesan ke-5 mendarat sebelum ke-4,
        // dan pasien menerima sapaan dengan urutan yang tidak masuk akal.
        $offset = 0;

        foreach ($pendingIds as $recipientId) {
            SendBroadcastRecipientJob::dispatch($recipientId, $broadcast->tenant_id)
                ->delay(now()->addSeconds($offset));

            $offset += random_int(
                self::MIN_SECONDS_BETWEEN_MESSAGES,
                self::MAX_SECONDS_BETWEEN_MESSAGES,
            );
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

    public function changeStatus(Broadcast $broadcast, BroadcastStatus $status): Broadcast
    {
        $old = $broadcast->status;

        // Resume = antrekan ulang sisa pending; job lama yang melihat status
        // jeda sudah gugur tanpa mengirim.
        if ($status === BroadcastStatus::Sending) {
            $this->queueSend($broadcast);

            return $broadcast->refresh();
        }

        $broadcast->update(['status' => $status, 'paused_reason' => null]);

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
     * Kirim template uji ke satu nomor — memastikan template yang dipilih
     * benar-benar diterima Meta sebelum ratusan pasien dikirimi.
     *
     * @param  array<string, mixed>  $template
     */
    public function sendTest(string $phone, array $template): void
    {
        $client = app(CloudApiClient::class);

        if (! $client instanceof CloudApiClient) {
            abort(422, __('broadcast.cloud_not_ready'));
        }

        // Header gambar butuh berkas yang benar-benar bisa diakses Meta.
        // Pesan uji belum punya poster tersimpan, jadi lebih jujur ditolak
        // daripada mengirim template yang pasti gagal.
        if (($template['header_type'] ?? null) === 'IMAGE') {
            abort(422, __('broadcast.template_test_image_unsupported'));
        }

        $client->sendTemplate(
            $phone,
            (string) $template['name'],
            (string) $template['language'],
            TemplateVariables::resolve(
                (array) ($template['params'] ?? []),
                TemplateVariables::examples(),
            ),
        );
    }
}
