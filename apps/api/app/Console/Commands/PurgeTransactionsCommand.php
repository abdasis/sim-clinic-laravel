<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bersihkan data transaksi POS satu klinik.
 *
 * Dipakai saat menyiapkan uji coba: memasukkan sebulan data percobaan lebih
 * mudah kalau riwayat lamanya benar-benar kosong, bukan bercampur.
 *
 * Menghapus transaksi tidak cukup dengan membuang barisnya. Penjualan produk
 * sudah memotong saldo stok lewat mutasi tersendiri; membuang transaksinya
 * saja meninggalkan saldo yang menyusut tanpa penjualan yang menjelaskannya.
 * Karena itu mutasi yang lahir dari transaksi ini ikut dibuang, lalu saldo
 * tiap produk terdampak dihitung ulang dari mutasi yang tersisa.
 *
 * Perintahnya merusak dan tidak bisa dibatalkan, jadi bawaannya hanya
 * menghitung; penghapusan sungguhan menuntut --force.
 */
class PurgeTransactionsCommand extends Command
{
    protected $signature = 'clinic:purge-transactions
        {--tenant= : Slug klinik yang datanya dibersihkan}
        {--before= : Hanya yang terbit sebelum tanggal ini (YYYY-MM-DD)}
        {--force : Hapus sungguhan; tanpa ini hanya menghitung}';

    protected $description = 'Hapus transaksi POS satu klinik beserta item, pembayaran, dan mutasi stoknya.';

    public function handle(): int
    {
        $tenant = Tenant::query()->where('slug', $this->option('tenant'))->first();

        if ($tenant === null) {
            $this->error('Klinik dengan slug "'.$this->option('tenant').'" tidak ditemukan.');
            $this->line('Slug yang tersedia: '.Tenant::query()->pluck('slug')->implode(', '));

            return self::FAILURE;
        }

        // Tenant diikat supaya seluruh model bertenant menyaring sendiri;
        // tanpa ini perintahnya bisa menyentuh klinik lain.
        app()->instance('tenant', $tenant);

        $query = Transaction::withTrashed();

        if ($before = $this->option('before')) {
            $query->whereDate('issued_at', '<', $before);
        }

        $ids = $query->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('Tidak ada transaksi yang cocok di klinik '.$tenant->name.'.');

            return self::SUCCESS;
        }

        $this->table(['Klinik', 'Transaksi', 'Batas tanggal'], [[
            $tenant->name,
            $ids->count(),
            $before ?: 'semua',
        ]]);

        if (! $this->option('force')) {
            $this->warn('Belum ada yang dihapus. Jalankan ulang dengan --force untuk menghapus sungguhan.');

            return self::SUCCESS;
        }

        $products = $this->purge($ids);

        $this->info('Terhapus: '.$ids->count().' transaksi.');
        $this->info('Saldo stok dihitung ulang untuk '.$products.' produk.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return int jumlah produk yang saldonya dihitung ulang
     */
    private function purge($ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $movements = StockMovement::query()
                ->where('related_type', 'transaction')
                ->whereIn('related_id', $ids)
                ->get();

            $productIds = $movements->pluck('product_id')->unique();

            StockMovement::query()
                ->where('related_type', 'transaction')
                ->whereIn('related_id', $ids)
                ->delete();

            // Item dan pembayaran ikut terbawa lewat cascade; performers pun.
            Transaction::withTrashed()->whereIn('id', $ids)->forceDelete();

            foreach ($productIds as $productId) {
                $this->recalculate($productId);
            }

            return $productIds->count();
        });
    }

    /**
     * Saldo produk disusun ulang dari mutasi yang tersisa, bukan ditambah
     * balik sejumlah yang dihapus: menambah balik mengandalkan saldo saat ini
     * sudah benar, padahal itu justru yang sedang diperbaiki.
     */
    private function recalculate(int $productId): void
    {
        $product = Product::withoutGlobalScopes()->find($productId);

        if ($product === null) {
            return;
        }

        $balance = 0;

        StockMovement::withoutGlobalScopes()
            ->where('product_id', $productId)
            ->orderBy('id')
            ->each(function (StockMovement $movement) use (&$balance): void {
                $balance += $movement->type->isInbound()
                    ? $movement->quantity
                    : -$movement->quantity;

                $movement->forceFill(['balance_after' => $balance])->save();
            });

        $product->forceFill(['stock_balance' => $balance])->save();
    }
}
