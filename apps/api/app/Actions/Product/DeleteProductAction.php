<?php

namespace App\Actions\Product;

use App\Actions\LogAuditAction;
use App\Models\Product;
use App\Support\CatalogReferences;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hapus permanen satu produk.
 *
 * Berbeda dari mengarsipkan: barisnya benar-benar hilang. Karena itu hanya
 * boleh untuk produk yang belum meninggalkan jejak — produk yang pernah
 * dijual atau masuk promo tetap dibutuhkan riwayatnya, dan untuk itu arsip
 * yang tepat.
 *
 * Kartu stoknya ikut dihapus. Mutasi stok adalah buku besar milik produk itu
 * sendiri dan tidak punya arti tanpa produknya — berbeda dari baris nota yang
 * merupakan bukti transaksi milik pasien. Foreign key-nya memakai RESTRICT di
 * PostgreSQL, jadi tanpa dibereskan lebih dulu penghapusannya berakhir sebagai
 * galat 500, bukan penolakan yang bisa dibaca.
 *
 * Baris nota dari transaksi yang sudah dihapus ikut dibuang dengan alasan yang
 * sama: notanya tidak muncul di mana pun lagi, tapi barisnya masih menahan
 * lewat foreign key yang sama.
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

        try {
            DB::transaction(function () use ($product): void {
                CatalogReferences::clearStaleProduct($product->id);
                $product->stockMovements()->delete();
                $product->delete();
            });
        } catch (Throwable $e) {
            Log::error('Gagal menghapus permanen produk.', [
                'exception' => $e,
                'product_id' => $product->id,
                'tenant_id' => $product->tenant_id,
            ]);

            throw $e;
        }

        app(LogAuditAction::class)->handle(
            'product.deleted',
            $product,
            Auth::user(),
            ['attributes' => $snapshot],
            'Menghapus permanen produk '.$name.' beserta kartu stoknya.',
        );
    }
}
