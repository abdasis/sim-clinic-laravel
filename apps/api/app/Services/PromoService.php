<?php

namespace App\Services;

use App\Actions\Promo\CreatePromoAction;
use App\Actions\Promo\DeletePromoAction;
use App\Actions\Promo\UpdatePromoAction;
use App\Models\Promo;
use Illuminate\Support\Facades\DB;

/**
 * Orkestrasi promo: satu promo dan daftar sasarannya ditulis bersama, jadi
 * batas transaksinya dipegang di sini.
 */
class PromoService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Promo
    {
        return DB::transaction(fn () => app(CreatePromoAction::class)->handle($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Promo $promo, array $data): Promo
    {
        return DB::transaction(fn () => app(UpdatePromoAction::class)->handle($promo, $data));
    }

    public function delete(Promo $promo): void
    {
        DB::transaction(fn () => app(DeletePromoAction::class)->handle($promo));
    }
}
