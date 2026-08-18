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

        // `category_id` bukan kolom di tabel ini melainkan baris pivot, jadi
        // tidak ikut terbawa mass assignment dan dipasang terpisah.
        if (array_key_exists('category_id', $data)) {
            $service->syncCategory($data['category_id'] !== null ? (int) $data['category_id'] : null);
        }

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
