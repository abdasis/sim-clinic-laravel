<?php

namespace App\Actions\Product;

use App\Actions\LogAuditAction;
use App\Enums\ServiceStatus;
use App\Models\Product;

/**
 * Arsipkan produk: status jadi archived, bukan hapus permanen, agar
 * riwayat mutasi dan penjualannya tetap utuh.
 */
class ArchiveProductAction
{
    public function handle(Product $product): Product
    {
        $old = $product->status;

        $product->update(['status' => ServiceStatus::Archived]);

        app(LogAuditAction::class)->handle(
            'product.archived',
            $product,
            auth()->user(),
            ['old' => ['status' => $old->value], 'new' => ['status' => ServiceStatus::Archived->value]],
            'Mengarsipkan produk '.$product->name.'.',
        );

        return $product;
    }
}
