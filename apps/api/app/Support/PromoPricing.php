<?php

namespace App\Support;

use App\Models\Promo;
use App\Models\PromoItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pencari promo yang berlaku untuk satu layanan/produk pada tanggal tertentu.
 *
 * Dibuat sebagai kelas baca, bukan Service use-case: ia tidak mengubah apa pun
 * dan dipakai baik oleh Resource katalog maupun oleh perhitungan transaksi,
 * sehingga harga yang dilihat kasir dan harga yang tersimpan berasal dari satu
 * sumber yang sama.
 *
 * ponytail: bila satu barang kena beberapa promo, yang dipakai adalah yang
 * paling menguntungkan pasien. Aturan penumpukan (stacking) sengaja belum ada
 * — tambahkan di sini bila nanti dibutuhkan.
 */
class PromoPricing
{
    /** @var array<string, Promo|null> */
    private array $cache = [];

    private Carbon $date;

    public function __construct(?Carbon $date = null)
    {
        $this->date = $date ?? Carbon::today();
    }

    /**
     * Promo terbaik untuk satu model, atau null bila tidak ada.
     */
    public function bestFor(Model $model): ?Promo
    {
        $alias = $model->getMorphClass();
        $key = $alias.':'.$model->getKey();

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $price = (float) $model->price;

        $promos = Promo::query()
            ->activeOn($this->date)
            ->whereHas('items', fn ($query) => $query
                ->where('promotable_type', $alias)
                ->where('promotable_id', $model->getKey()))
            ->get();

        return $this->cache[$key] = $this->cheapest($promos, $price);
    }

    /**
     * Harga setelah promo; sama dengan harga asli bila tidak ada promo.
     */
    public function priceFor(Model $model): float
    {
        $price = (float) $model->price;

        return $this->bestFor($model)?->applyTo($price) ?? $price;
    }

    /**
     * Muat sekaligus untuk banyak model agar daftar katalog tidak menembak
     * satu kueri per baris.
     *
     * @param  Collection<int, Model>  $models
     */
    public function preload(Collection $models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $byAlias = $models->groupBy(fn (Model $model) => $model->getMorphClass());

        $items = PromoItem::query()
            ->whereHas('promo', fn ($query) => $query->activeOn($this->date))
            ->where(function ($query) use ($byAlias) {
                foreach ($byAlias as $alias => $group) {
                    $query->orWhere(fn ($inner) => $inner
                        ->where('promotable_type', $alias)
                        ->whereIn('promotable_id', $group->map->getKey()->all()));
                }
            })
            ->with('promo')
            ->get()
            ->groupBy(fn (PromoItem $item) => $item->promotable_type.':'.$item->promotable_id);

        foreach ($models as $model) {
            $key = $model->getMorphClass().':'.$model->getKey();
            $promos = ($items[$key] ?? collect())->map->promo->filter();

            $this->cache[$key] = $this->cheapest($promos, (float) $model->price);
        }
    }

    /**
     * @param  Collection<int, Promo>  $promos
     */
    private function cheapest(Collection $promos, float $price): ?Promo
    {
        return $promos
            ->sortBy(fn (Promo $promo) => $promo->applyTo($price))
            ->first();
    }
}
