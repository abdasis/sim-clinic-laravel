<?php

namespace App\Actions\Service;

use App\Actions\LogAuditAction;
use App\Models\Service;

/**
 * Tambah layanan baru ke katalog klinik.
 */
class CreateServiceAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Service
    {
        $service = Service::create($data);

        app(LogAuditAction::class)->handle(
            'service.created',
            $service,
            null,
            $service->only(['name', 'description', 'price', 'duration_minutes', 'status']),
            'Membuat layanan '.$service->name.'.',
        );

        return $service;
    }
}
