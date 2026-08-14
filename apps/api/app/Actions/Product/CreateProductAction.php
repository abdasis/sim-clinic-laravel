<?php

namespace App\Actions\Product;

use App\Actions\LogAuditAction;
use App\Models\Product;

/**
 * Tambah produk baru. Saldo stok tidak pernah diisi dari form — hanya
 * tumbuh lewat mutasi stok, supaya riwayatnya selalu dapat ditelusuri.
 */
class CreateProductAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Product
    {
        unset($attributes['stock_balance']);

        $product = Product::create($attributes);

        // Saldo stok dan status berasal dari default kolom, jadi instance
        // hasil create belum memuatnya sampai di-refresh.
        $product->refresh();

        app(LogAuditAction::class)->handle(
            'product.created',
            $product,
            auth()->user(),
            ['attributes' => $product->getAttributes()],
            'Membuat produk '.$product->name.'.',
        );

        return $product;
    }
}
