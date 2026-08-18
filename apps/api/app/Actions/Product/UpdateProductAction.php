<?php

namespace App\Actions\Product;

use App\Actions\LogAuditAction;
use App\Models\Product;

/**
 * Perbarui data master produk. Saldo stok sengaja diabaikan agar tidak
 * bisa diubah diam-diam tanpa mutasi.
 */
class UpdateProductAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Product $product, array $attributes): Product
    {
        unset($attributes['stock_balance']);

        $old = $product->only(array_keys($attributes));

        $product->update($attributes);

        // `category_id` bukan kolom di tabel ini melainkan baris pivot, jadi
        // tidak ikut terbawa mass assignment dan dipasang terpisah.
        if (array_key_exists('category_id', $attributes)) {
            $product->syncCategory($attributes['category_id'] !== null ? (int) $attributes['category_id'] : null);
        }

        app(LogAuditAction::class)->handle(
            'product.updated',
            $product->fresh(),
            auth()->user(),
            ['old' => $old, 'new' => $product->fresh()->only(array_keys($attributes))],
            'Memperbarui produk '.$product->name.'.',
        );

        return $product;
    }
}
