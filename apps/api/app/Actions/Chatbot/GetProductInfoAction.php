<?php

namespace App\Actions\Chatbot;

use App\Models\Product;
use App\Support\RichTextPlain;

/**
 * Informasi dan manfaat produk, untuk menjawab "ini buat apa?".
 *
 * Dipisah dari tool stok dengan sengaja: yang satu menjawab angka dan harus
 * ringan, yang ini menjawab paragraf. Menggabungkannya membuat setiap
 * pertanyaan harga ikut menyeret teks panjang yang tidak dipakai.
 */
class GetProductInfoAction
{
    /** Lebih kecil dari batas tool stok — teksnya jauh lebih panjang. */
    private const LIMIT = 10;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(?string $keyword = null): array
    {
        return Product::query()
            ->when(filled($keyword), fn ($query) => $query->where('name', 'like', '%'.$keyword.'%'))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'knowledge'])
            ->map(fn (Product $product): array => [
                'name' => $product->name,
                // null berarti belum pernah ditulis; AI harus menyatakan
                // informasinya tidak tersedia, bukan mengarang manfaat.
                'knowledge' => RichTextPlain::extract($product->knowledge),
            ])
            ->all();
    }
}
