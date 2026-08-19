<?php

namespace App\Actions\Chatbot;

use App\Models\Promo;
use App\Models\PromoItem;

/**
 * Promo yang sedang berjalan hari ini.
 *
 * Yang sudah lewat atau belum mulai sengaja tidak ikut: pasien membacanya
 * sebagai tawaran yang bisa dipakai sekarang, dan menyebut promo kedaluwarsa
 * berakhir jadi kekecewaan di meja kasir.
 */
class GetActivePromosAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(): array
    {
        return Promo::query()
            ->activeOn()
            ->with('items.promotable')
            ->orderBy('name')
            ->get()
            ->map(fn (Promo $promo): array => [
                'name' => $promo->name,
                'description' => $promo->description,
                'discount_type' => $promo->discount_type?->value,
                'discount_value' => (float) $promo->discount_value,
                'starts_at' => $promo->starts_at?->format('Y-m-d'),
                'ends_at' => $promo->ends_at?->format('Y-m-d'),
                // Sasarannya disebut supaya AI tidak menjanjikan diskon untuk
                // treatment yang sebenarnya tidak ikut promo.
                'targets' => $promo->items
                    ->map(fn (PromoItem $item): array => [
                        'type' => $item->promotable_type,
                        'name' => $item->promotable?->name,
                    ])
                    ->filter(fn (array $target) => $target['name'] !== null)
                    ->values()
                    ->all(),
            ])
            ->all();
    }
}
