<?php

namespace App\Actions\Service;

use App\Actions\LogAuditAction;
use App\Models\Service;

/**
 * Perbarui data master layanan. Snapshot pada transaksi dan treatment
 * yang sudah terjadi tidak ikut berubah.
 */
class UpdateServiceAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Service $service, array $data): Service
    {
        $old = $service->only(array_keys($data));

        $service->update($data);

        app(LogAuditAction::class)->handle(
            'service.updated',
            $service,
            null,
            ['old' => $old, 'new' => $service->only(array_keys($data))],
            'Memperbarui layanan '.$service->name.'.',
        );

        return $service;
    }
}
