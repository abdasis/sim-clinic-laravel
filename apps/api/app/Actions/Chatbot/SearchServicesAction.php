<?php

namespace App\Actions\Chatbot;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Support\PromoPricing;
use App\Support\PromoQuote;

/**
 * Cari layanan aktif klinik untuk dijawabkan ke pasien.
 *
 * Layanan terarsip sengaja tidak ikut: kalau ikut, chatbot akan menawarkan
 * treatment yang sudah tidak dijual lagi lengkap dengan harganya.
 *
 * Harganya dihitung lewat PromoPricing, sumber yang sama dengan kasir.
 * Sebelumnya yang disebut selalu harga katalog, jadi pasien dijanjikan angka
 * yang tidak pernah cocok dengan notanya begitu promo sedang berjalan.
 */
class SearchServicesAction
{
    private const LIMIT = 20;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(?string $keyword = null): array
    {
        $services = Service::query()
            ->where('status', ServiceStatus::Active)
            ->when(filled($keyword), fn ($query) => $query->where('name', 'like', '%'.$keyword.'%'))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'price', 'duration_minutes']);

        // Sekali muat untuk seluruh daftar; satu kueri promo per baris akan
        // membuat satu pertanyaan pasien menembak dua puluh kueri.
        $pricing = new PromoPricing;
        $pricing->preload($services);

        return $services
            // Durasi sengaja tidak ikut: pasien hanya perlu tahu jam
            // bookingnya, bukan berapa menit treatment-nya berlangsung.
            // Panjang slot dihitung server saat check_availability, jadi AI
            // tidak pernah membutuhkannya untuk menjadwalkan.
            ->map(fn (Service $service): array => PromoQuote::describe($service, $pricing, [
                'id' => $service->id,
                'name' => $service->name,
            ]))
            ->all();
    }
}
