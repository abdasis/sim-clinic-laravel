<?php

namespace App\Actions;

use App\Enums\ServiceStatus;
use App\Models\Service;

/**
 * Arsipkan layanan (FR-013): set status=archived, bukan hapus permanen.
 */
class ArchiveServiceAction
{
    public function handle(Service $service): Service
    {
        $service->update(['status' => ServiceStatus::Archived]);

        return $service;
    }
}
