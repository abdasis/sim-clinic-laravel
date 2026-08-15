<?php

namespace App\Actions\Promo;

use App\Actions\LogAuditAction;
use App\Models\Promo;

/**
 * Hapus promo. Sasarannya ikut terhapus lewat foreign key cascade.
 */
class DeletePromoAction
{
    public function handle(Promo $promo): void
    {
        $snapshot = $promo->getAttributes();
        $name = $promo->name;

        $promo->delete();

        app(LogAuditAction::class)->handle(
            'promo.deleted',
            null,
            auth()->user(),
            ['attributes' => $snapshot],
            'Menghapus promo '.$name.'.',
        );
    }
}
