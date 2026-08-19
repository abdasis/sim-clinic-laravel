<?php

namespace App\Actions\Product;

use App\Actions\LogAuditAction;
use App\Models\Product;
use App\Support\CatalogReferences;
use Illuminate\Support\Facades\Auth;

/**
 * Hapus permanen satu produk.
 *
 * Berbeda dari mengarsipkan: barisnya benar-benar hilang. Karena itu hanya
 * boleh untuk produk yang belum meninggalkan jejak — produk yang pernah
 * dijual atau masuk promo tetap
 * dibutuhkan riwayatnya, dan untuk itu arsip yang tepat.
 */
class DeleteProductAction
{
    public function handle(Product $product): void
    {
        if ($reason = CatalogReferences::blockingProduct($product->id)) {
            abort(422, $reason);
        }

        $snapshot = $product->getAttributes();
        $name = $product->name;

        $product->delete();

        app(LogAuditAction::class)->handle(
            'product.deleted',
            $product,
            Auth::user(),
            ['attributes' => $snapshot],
            'Menghapus permanen produk '.$name.'.',
        );
    }
}
