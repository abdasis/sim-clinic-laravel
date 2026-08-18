<?php

namespace App\Services;

use App\Actions\Service\ArchiveServiceAction;
use App\Actions\Service\CreateServiceAction;
use App\Actions\Service\DeleteServiceAction;
use App\Actions\Service\UpdateServiceAction;
use App\Models\Service;

/**
 * Use case katalog layanan klinik.
 */
class ServiceCatalogService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Service
    {
        return app(CreateServiceAction::class)->handle($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Service $service, array $data): Service
    {
        return app(UpdateServiceAction::class)->handle($service, $data);
    }

    /** Hapus permanen; ditolak bila entrinya sudah meninggalkan jejak. */
    public function delete(Service $service): void
    {
        app(DeleteServiceAction::class)->handle($service);
    }

    public function archive(Service $service): Service
    {
        return app(ArchiveServiceAction::class)->handle($service);
    }
}
