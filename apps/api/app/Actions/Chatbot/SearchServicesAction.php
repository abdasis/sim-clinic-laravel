<?php

namespace App\Actions\Chatbot;

use App\Enums\ServiceStatus;
use App\Models\ChatbotSetting;
use App\Models\Service;
use App\Support\PromoPricing;
use App\Support\PromoQuote;
use App\Support\Search;

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
            ->when(filled($keyword), fn ($query) => Search::apply($query, ['name'], $keyword))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'price', 'duration_minutes']);

        // Sekali muat untuk seluruh daftar; satu kueri promo per baris akan
        // membuat satu pertanyaan pasien menembak dua puluh kueri.
        $pricing = new PromoPricing;
        $pricing->preload($services);

        // Boleh-tidaknya dibooking lewat chat ikut disebut sejak awal.
        // Sebelumnya penjagaannya hanya ada di detik terakhir, jadi chatbot
        // sempat menawarkan jadwal untuk layanan yang ternyata tidak bisa
        // dibookingnya — dan pasien baru tahu setelah menyetujui jamnya.
        $setting = ChatbotSetting::query()->first();

        return $services
            // Durasi sengaja tidak ikut: pasien hanya perlu tahu jam
            // bookingnya, bukan berapa menit treatment-nya berlangsung.
            // Panjang slot dihitung server saat check_availability, jadi AI
            // tidak pernah membutuhkannya untuk menjadwalkan.
            ->map(fn (Service $service): array => PromoQuote::describe($service, $pricing, [
                'id' => $service->id,
                'name' => $service->name,
                'bookable_via_chat' => $setting?->allowsBooking($service->id) ?? true,
            ]))
            ->all();
    }
}
