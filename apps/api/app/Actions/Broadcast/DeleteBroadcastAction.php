<?php

namespace App\Actions\Broadcast;

use App\Actions\LogAuditAction;
use App\Models\Broadcast;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
        $image = $broadcast->image_path;

        $broadcast->delete();

        // Posternya ikut dibuang: tidak ada lagi yang menunjuk ke sana, dan
        // berkas yatim di disk hanya menumpuk tanpa pernah ketahuan.
        if ($image !== null) {
            Storage::disk('public')->delete($image);
        }

        app(LogAuditAction::class)->handle(
            'broadcast.deleted',
            null,
            Auth::user(),
            ['attributes' => $snapshot],
            'Menghapus broadcast '.$title.'.',
        );
    }
}
