<?php

namespace App\Actions\Chatbot;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Support\RichTextPlain;
use App\Support\Search;

/**
 * Informasi dan manfaat layanan, untuk menjawab "treatment ini buat apa?".
 *
 * Dipisah dari search_services dengan sengaja: yang satu menjawab harga dan
 * durasi dan harus ringan, yang ini menjawab paragraf. Menggabungkannya
 * membuat setiap pertanyaan harga ikut menyeret teks panjang yang tidak
 * dipakai.
 *
 * Layanan terarsip tidak ikut — beda dari produk, yang tidak menyaring
 * status. Alasannya: chatbot yang menerangkan manfaat treatment sedang
 * menawarkannya, dan menawarkan yang sudah tidak dijual membuat pasien
 * datang untuk sesuatu yang tidak ada.
 */
class GetServiceInfoAction
{
    /** Lebih kecil dari batas pencarian layanan — teksnya jauh lebih panjang. */
    private const LIMIT = 10;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(?string $keyword = null): array
    {
        return Service::query()
            ->where('status', ServiceStatus::Active)
            ->when(filled($keyword), fn ($query) => Search::apply($query, ['name'], $keyword))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'knowledge'])
            ->map(fn (Service $service): array => [
                'name' => $service->name,
                // null berarti belum pernah ditulis; AI harus menyatakan
                // informasinya tidak tersedia, bukan mengarang manfaat.
                'knowledge' => RichTextPlain::extract($service->knowledge),
            ])
            ->all();
    }
}
