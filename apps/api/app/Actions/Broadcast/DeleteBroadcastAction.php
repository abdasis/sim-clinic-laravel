<?php

namespace App\Actions\Broadcast;

use App\Actions\LogAuditAction;
use App\Models\Broadcast;
use Illuminate\Support\Facades\Auth;

/**
 * Hapus broadcast beserta penerimanya (cascade). Riwayat pengiriman tetap
 * terbaca lewat log audit yang menyimpan ringkasannya.
 */
class DeleteBroadcastAction
{
    public function handle(Broadcast $broadcast): void
    {
        $snapshot = $broadcast->getAttributes();
        $title = $broadcast->title;

        $broadcast->delete();

        app(LogAuditAction::class)->handle(
            'broadcast.deleted',
            null,
            Auth::user(),
            ['attributes' => $snapshot],
            'Menghapus broadcast '.$title.'.',
        );
    }
}
