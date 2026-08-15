<?php

namespace App\Actions\Promo;

use App\Actions\LogAuditAction;
use App\Models\Promo;

/**
 * Perbarui promo. Transaksi yang sudah terjadi tidak ikut berubah karena
 * harganya sudah disnapshot di baris transaksi.
 */
class UpdatePromoAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Promo $promo, array $data): Promo
    {
        $targets = $data['targets'] ?? null;
        unset($data['targets']);

        $old = $promo->only(array_keys($data));

        $promo->update($data);

        if ($targets !== null) {
            app(SyncPromoTargetsAction::class)->handle($promo, $targets);
        }

        app(LogAuditAction::class)->handle(
            'promo.updated',
            $promo,
            auth()->user(),
            ['old' => $old, 'new' => $promo->only(array_keys($data)), 'targets' => $targets],
            'Memperbarui promo '.$promo->name.'.',
        );

        return $promo->load('items.promotable');
    }
}
