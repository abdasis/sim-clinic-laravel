<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Bentuk harga satu barang/layanan untuk dijawabkan chatbot.
 *
 * Chatbot dulu menyebut harga katalog apa adanya, sehingga pasien dijanjikan
 * angka yang tidak pernah cocok dengan notanya begitu promo sedang berjalan.
 * Harganya kini datang dari PromoPricing — sumber yang sama dengan kasir —
 * dan promonya ikut disebut namanya supaya AI bisa mengatakan kenapa
 * harganya lebih murah, bukan sekadar menyebut angka yang berbeda.
 */
class PromoQuote
{
    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public static function describe(Model $model, PromoPricing $pricing, array $base = []): array
    {
        $normal = (float) $model->price;
        $promo = $pricing->bestFor($model);
        $price = $promo?->applyTo($normal) ?? $normal;

        $row = [...$base, 'price' => $price];

        if ($promo === null) {
            return $row;
        }

        // Harga sebelum potongan ikut disebut: tanpa pembanding, "diskon"
        // hanya jadi kata tanpa isi di mata pasien.
        $row['normal_price'] = $normal;
        $row['promo'] = [
            'name' => $promo->name,
            'ends_at' => $promo->ends_at?->format('Y-m-d'),
        ];

        return $row;
    }
}
