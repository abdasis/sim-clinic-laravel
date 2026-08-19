<?php

namespace App\Actions\Service;

use App\Actions\LogAuditAction;
use App\Models\Service;
use App\Support\CatalogReferences;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hapus permanen satu layanan.
 *
 * Berbeda dari mengarsipkan: barisnya benar-benar hilang. Karena itu hanya
 * boleh untuk layanan yang belum meninggalkan jejak — layanan yang pernah
 * dijual, dijadwalkan, dicatat di rekam medis, atau masuk promo tetap
 * dibutuhkan riwayatnya, dan untuk itu arsip yang tepat.
 *
 * Jejak dari nota atau rekam medis yang sudah dihapus bukan riwayat hidup:
 * barisnya ikut dibuang di sini, karena foreign key-nya restrict dan tanpa itu
 * penghapusan tertahan atas nama data yang sudah tidak ada.
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

        try {
            DB::transaction(function () use ($service): void {
                CatalogReferences::clearStaleService($service->id);
                $service->delete();
            });
        } catch (Throwable $e) {
            Log::error('Gagal menghapus permanen layanan.', [
                'exception' => $e,
                'service_id' => $service->id,
                'tenant_id' => $service->tenant_id,
            ]);

            throw $e;
        }

        app(LogAuditAction::class)->handle(
            'service.deleted',
            $service,
            Auth::user(),
            ['attributes' => $snapshot],
            'Menghapus permanen layanan '.$name.'.',
        );
    }
}
