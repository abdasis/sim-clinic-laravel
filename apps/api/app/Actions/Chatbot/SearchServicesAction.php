<?php

namespace App\Actions\Chatbot;

use App\Enums\ServiceStatus;
use App\Models\Service;

/**
 * Cari layanan aktif klinik untuk dijawabkan ke pasien.
 *
 * Layanan terarsip sengaja tidak ikut: kalau ikut, chatbot akan menawarkan
 * treatment yang sudah tidak dijual lagi lengkap dengan harganya.
 */
class SearchServicesAction
{
    private const LIMIT = 20;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(?string $keyword = null): array
    {
        return Service::query()
            ->where('status', ServiceStatus::Active)
            ->when(filled($keyword), fn ($query) => $query->where('name', 'like', '%'.$keyword.'%'))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'price', 'duration_minutes'])
            ->map(fn (Service $service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'price' => (float) $service->price,
                'duration_minutes' => $service->duration_minutes,
            ])
            ->all();
    }
}
