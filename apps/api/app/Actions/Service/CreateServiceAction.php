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
            auth()->user(),
            ['attributes' => $service->getAttributes()],
            'Membuat layanan '.$service->name.' dengan durasi '.$service->duration_minutes.' menit.',
        );

        return $service;
    }
}
