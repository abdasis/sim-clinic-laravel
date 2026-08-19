<?php

namespace App\Actions\Service;

use App\Actions\LogAuditAction;
use App\Models\Service;
use App\Support\CatalogReferences;
use Illuminate\Support\Facades\Auth;

/**
 * Hapus permanen satu layanan.
 *
 * Berbeda dari mengarsipkan: barisnya benar-benar hilang. Karena itu hanya
 * boleh untuk layanan yang belum meninggalkan jejak — layanan yang pernah
 * dijual, dijadwalkan, dicatat di rekam medis, atau masuk promo tetap
 * dibutuhkan riwayatnya, dan untuk itu arsip yang tepat.
 */
class DeleteServiceAction
{
    public function handle(Service $service): void
    {
        if ($reason = CatalogReferences::blockingService($service->id)) {
            abort(422, $reason);
        }

        $snapshot = $service->getAttributes();
        $name = $service->name;

        $service->delete();

        app(LogAuditAction::class)->handle(
            'service.deleted',
            $service,
            Auth::user(),
            ['attributes' => $snapshot],
            'Menghapus permanen layanan '.$name.'.',
        );
    }
}
