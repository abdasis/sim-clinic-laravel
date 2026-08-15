<?php

namespace App\Actions\Promo;

use App\Actions\LogAuditAction;
use App\Models\Promo;

/**
 * Buat promo baru beserta daftar sasarannya.
 */
class CreatePromoAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Promo
    {
        $targets = $data['targets'] ?? [];
        unset($data['targets']);

        $promo = Promo::create($data);

        // Status boleh dikosongkan form dan diisi default kolomnya; tanpa
        // refresh, model di memori tidak tahu nilai itu dan promo baru terbaca
        // seolah belum berjalan.
        $promo->refresh();

        app(SyncPromoTargetsAction::class)->handle($promo, $targets);

        app(LogAuditAction::class)->handle(
            'promo.created',
            $promo,
            auth()->user(),
            ['attributes' => $promo->getAttributes(), 'targets' => $targets],
            'Membuat promo '.$promo->name.', berlaku '
                .$promo->starts_at?->format('d/m/Y').' sampai '
                .$promo->ends_at?->format('d/m/Y').'.',
        );

        return $promo->load('items.promotable');
    }
}
