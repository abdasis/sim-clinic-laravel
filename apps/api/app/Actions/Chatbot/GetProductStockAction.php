<?php

namespace App\Actions\Chatbot;

use App\Models\Product;

/**
 * Ketersediaan produk yang ditanyakan pasien.
 *
 * Angka stoknya ikut disebut apa adanya; yang menentukan kalimat balasannya
 * tetap AI, tapi dasarnya harus angka nyata dari kartu stok.
 */
class GetProductStockAction
{
    private const LIMIT = 20;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(?string $keyword = null): array
    {
        return Product::query()
            ->when(filled($keyword), fn ($query) => $query->where('name', 'like', '%'.$keyword.'%'))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Product $product): array => [
                'name' => $product->name,
                'unit' => $product->unit,
                'price' => (float) $product->price,
                'stock_balance' => $product->stock_balance,
                'is_low_stock' => $product->is_low_stock,
            ])
            ->all();
    }
}
