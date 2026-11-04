<?php

namespace App\Actions\Broadcast;

use App\Actions\LogAuditAction;
use App\Enums\BroadcastRecipientStatus;
use App\Models\Broadcast;
use Illuminate\Support\Facades\DB;

/**
 * Kembalikan penerima yang gagal ke keadaan menunggu supaya bisa diantrekan
 * lagi.
 *
 * Menekan kirim setelah sesi WhatsApp pulih memang bermaksud mengulang yang
 * tidak sampai. Tanpa ini campaign yang seluruhnya gagal jadi jalan buntu —
 * satu-satunya jalan keluar menandai ulang penerimanya satu per satu.
 *
 * Nomor percobaan kirim ikut dinaikkan: kunci idempotensi gateway memakainya,
 * jadi pengiriman ulang sungguhan tidak dibuang sebagai duplikat pesan lama.
 */
class RequeueFailedRecipientsAction
{
    /** @return int jumlah penerima yang dikembalikan ke antrean */
    public function handle(Broadcast $broadcast): int
    {
        $failed = $broadcast->recipients()
            ->where('status', BroadcastRecipientStatus::Failed)
            ->count();

        if ($failed === 0) {
            return 0;
        }

        $broadcast->recipients()
            ->where('status', BroadcastRecipientStatus::Failed)
            ->update([
                'status' => BroadcastRecipientStatus::Pending,
                'error' => null,
                // Bulking lewat query builder, jadi kenaikan kolomnya ditulis
                // sebagai ekspresi SQL; satu perintah, bukan N kueri.
                'send_attempt' => DB::raw('send_attempt + 1'),
            ]);

        app(LogAuditAction::class)->handle(
            'broadcast.recipients_requeued',
            $broadcast,
            auth()->user(),
            ['new' => ['requeued' => $failed]],
            'Mengantrekan ulang '.$failed.' penerima yang gagal pada broadcast '.$broadcast->title.'.',
        );

        return $failed;
    }
}
