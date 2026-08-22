<?php

namespace App\Actions\Chatbot;

use App\Models\ChatbotSetting;
use App\Models\Product;
use App\Support\PromoPricing;
use App\Support\PromoQuote;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ketersediaan produk yang ditanyakan pasien.
 *
 * Angka stoknya disebut apa adanya bila klinik mengizinkan; yang menentukan
 * kalimat balasannya tetap AI, tapi dasarnya harus angka nyata dari kartu
 * stok. Sebagian klinik tidak ingin persediaannya terbaca dari luar, jadi
 * angkanya disaring di sini — bukan cuma dilarang lewat prompt, karena
 * larangan di prompt masih bisa dilanggar model.
 */
class GetProductStockAction
{
    private const LIMIT = 20;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(?string $keyword = null): array
    {
        // ponytail: setelan dibaca di dalam Action, bukan dioper lewat
        // parameter, supaya perubahan ini tidak merembet ke signature
        // ChatTools::execute() dan seluruh tool lain yang tidak
        // membutuhkannya. Batasnya: begitu ada tool kedua yang juga perlu
        // membaca setelan, pindahkan jadi flag yang dioper dari execute()
        // agar tidak ada dua kueri setelan dalam satu putaran percakapan.
        $showsStock = ChatbotSetting::query()->first()?->allows_stock_info ?? true;

        $products = Product::query()
            ->tap(fn (Builder $query) => Search::apply($query, 'name', $keyword))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();

        // Harga promo dari sumber yang sama dengan kasir; dimuat sekali untuk
        // seluruh daftar supaya satu pertanyaan tidak jadi dua puluh kueri.
        $pricing = new PromoPricing;
        $pricing->preload($products);

        return $products
            ->map(function (Product $product) use ($showsStock, $pricing): array {
                $row = PromoQuote::describe($product, $pricing, [
                    'name' => $product->name,
                    'unit' => $product->unit,
                ]);

                $row['stock_balance'] = $product->stock_balance;
                $row['is_low_stock'] = $product->is_low_stock;

                if (! $showsStock) {
                    unset($row['stock_balance'], $row['is_low_stock']);

                    // Angkanya disembunyikan, ada-tidaknya barang tidak.
                    // Tanpa penanda ini AI kehilangan cara membedakan produk
                    // yang habis dari yang tersedia, dan ia akan menjawab
                    // "tersedia" untuk barang kosong — pasien datang ke
                    // klinik untuk sesuatu yang tidak ada.
                    $row['is_available'] = $product->stock_balance > 0;
                }

                return $row;
            })
            ->all();
    }
}
