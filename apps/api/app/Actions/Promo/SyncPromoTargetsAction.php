<?php

namespace App\Actions\Promo;

use App\Models\Product;
use App\Models\Promo;
use App\Models\Service;

/**
 * Samakan daftar sasaran promo dengan yang dikirim form. Ditulis ulang penuh
 * karena form selalu mengirim daftar lengkap, bukan selisihnya.
 *
 * @phpstan-type Target array{type: string, id: int}
 */
class SyncPromoTargetsAction
{
    private const MODELS = [
        'service' => Service::class,
        'product' => Product::class,
    ];

    /**
     * @param  array<int, array{type: string, id: int|string}>  $targets
     */
    public function handle(Promo $promo, array $targets): void
    {
        $promo->items()->delete();

        $seen = [];

        foreach ($targets as $target) {
            $type = $target['type'] ?? null;
            $id = (int) ($target['id'] ?? 0);

            if (! isset(self::MODELS[$type]) || $id <= 0) {
                continue;
            }

            // Baris kembar ditolak oleh unique index; disaring lebih dulu agar
            // form yang mengirim duplikat tidak berujung 500.
            $key = $type.':'.$id;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $promo->items()->create([
                'promotable_type' => $type,
                'promotable_id' => $id,
            ]);
        }
    }
}
