<?php

namespace App\Actions\Service;

use App\Actions\LogAuditAction;
use App\Enums\ServiceStatus;
use App\Models\Service;

/**
 * Arsipkan layanan (FR-013): set status=archived, bukan hapus permanen,
 * supaya riwayat booking dan transaksi tetap utuh.
 */
class ArchiveServiceAction
{
    public function handle(Service $service): Service
    {
        $old = $service->status;

        $service->update(['status' => ServiceStatus::Archived]);

        app(LogAuditAction::class)->handle(
            'service.archived',
            $service,
            null,
            ['old' => ['status' => $old->value], 'new' => ['status' => ServiceStatus::Archived->value]],
            'Mengarsipkan layanan '.$service->name.'.',
        );

        return $service;
    }
}
