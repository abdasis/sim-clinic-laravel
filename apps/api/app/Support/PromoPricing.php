<?php

namespace App\Support;

use App\Enums\PromoStatus;
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
 * Selain promo yang sedang berlaku, ia juga menyebut promo yang TIDAK sedang
 * berlaku — yang sudah berakhir atau belum mulai. Tanpa itu kasir hanya
 * melihat harga penuh tanpa penjelasan, dan promo yang kedaluwarsa terbaca
 * sebagai hitungan yang salah.
 *
 * ponytail: bila satu barang kena beberapa promo, yang dipakai adalah yang
 * paling menguntungkan pasien. Aturan penumpukan (stacking) sengaja belum ada
 * — tambahkan di sini bila nanti dibutuhkan.
 */
class PromoPricing
{
    /** @var array<string, array{aktif: ?Promo, tidur: ?Promo}> */
    private array $cache = [];

    private Carbon $date;

    public function __construct(?Carbon $date = null)
    {
        $this->date = $date ?? Carbon::today();
    }

    /**
     * Promo terbaik yang sedang berlaku, atau null bila tidak ada.
     */
    public function bestFor(Model $model): ?Promo
    {
        return $this->resolve($model)['aktif'];
    }

    /**
     * Promo yang terpasang pada barang ini tapi sedang TIDAK berlaku —
     * sudah berakhir atau belum mulai.
     *
     * Hanya dijawab saat tidak ada promo yang sedang berlaku: menyebut promo
     * kedaluwarsa di samping promo yang sedang jalan hanya menambah keraguan
     * di layar yang sedang dipakai menagih.
     */
    public function dormantFor(Model $model): ?Promo
    {
        $hasil = $this->resolve($model);

        return $hasil['aktif'] === null ? $hasil['tidur'] : null;
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

        // Tanggalnya tidak disaring di kueri: yang sudah lewat dan yang belum
        // mulai justru yang hendak disebut. Penyaringannya di PHP, sekali,
        // bersama pemilihan promo terbaiknya.
        $items = PromoItem::query()
            ->whereHas('promo', fn ($query) => $query->where('status', PromoStatus::Active))
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
            $key = $this->keyOf($model);
            $promos = ($items[$key] ?? collect())->map->promo->filter();

            $this->cache[$key] = $this->pilah($promos, (float) $model->price);
        }
    }

    /**
     * @return array{aktif: ?Promo, tidur: ?Promo}
     */
    private function resolve(Model $model): array
    {
        $key = $this->keyOf($model);

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $promos = Promo::query()
            ->where('status', PromoStatus::Active)
            ->whereHas('items', fn ($query) => $query
                ->where('promotable_type', $model->getMorphClass())
                ->where('promotable_id', $model->getKey()))
            ->get();

        return $this->cache[$key] = $this->pilah($promos, (float) $model->price);
    }

    private function keyOf(Model $model): string
    {
        return $model->getMorphClass().':'.$model->getKey();
    }

    /**
     * Pisahkan promo yang sedang berlaku dari yang tidak.
     *
     * @param  Collection<int, Promo>  $promos
     * @return array{aktif: ?Promo, tidur: ?Promo}
     */
    private function pilah(Collection $promos, float $price): array
    {
        [$berlaku, $tidak] = $promos->partition(
            fn (Promo $promo) => $promo->isRunningOn($this->date)
        );

        return [
            'aktif' => $this->cheapest($berlaku, $price),
            'tidur' => $this->terdekat($tidak),
        ];
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

    /**
     * Promo tak-berlaku yang batas waktunya paling dekat dengan hari ini.
     *
     * Jaraknya yang dipakai, bukan arahnya: promo yang baru berakhir kemarin
     * lebih menjelaskan harga hari ini daripada promo yang baru mulai bulan
     * depan, dan sebaliknya. Yang paling dekat hampir selalu yang dimaksud
     * orang yang bertanya.
     *
     * @param  Collection<int, Promo>  $promos
     */
    private function terdekat(Collection $promos): ?Promo
    {
        return $promos
            ->sortBy(fn (Promo $promo) => $this->jarakHari($promo))
            ->first();
    }

    private function jarakHari(Promo $promo): int
    {
        // Belum mulai: hitung ke tanggal mulainya. Sudah berakhir: ke tanggal
        // berakhirnya. Tanggal yang kosong dianggap tak terhingga supaya promo
        // setengah terisi tidak pernah menang atas yang lengkap.
        $batas = $promo->starts_at?->greaterThan($this->date) === true
            ? $promo->starts_at
            : $promo->ends_at;

        return $batas === null ? PHP_INT_MAX : (int) abs($this->date->diffInDays($batas));
    }
}
