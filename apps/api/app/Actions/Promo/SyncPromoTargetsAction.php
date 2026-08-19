<?php

namespace App\Actions\Promo;

use App\Actions\LogAuditAction;
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
        $before = $this->currentTargets($promo);

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

        $after = $this->currentTargets($promo->refresh());

        // Ditulis ulang penuh, jadi log hanya berguna kalau isinya benar-benar
        // berubah — menyimpan daftar yang sama persis cuma menambah derau.
        if ($before === $after) {
            return;
        }

        app(LogAuditAction::class)->handle(
            'promo.targets_synced',
            $promo,
            auth()->user(),
            ['old' => ['targets' => $before], 'new' => ['targets' => $after]],
            'Memperbarui sasaran promo '.$promo->name.' menjadi '.count($after).' item.',
        );
    }

    /**
     * @return array<int, string> daftar "type:id" yang sudah diurutkan
     */
    private function currentTargets(Promo $promo): array
    {
        $targets = $promo->items()
            ->get(['promotable_type', 'promotable_id'])
            ->map(fn ($item) => $item->promotable_type.':'.$item->promotable_id)
            ->all();

        sort($targets);

        return $targets;
    }
}
