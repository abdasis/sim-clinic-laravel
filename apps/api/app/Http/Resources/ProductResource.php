<?php

namespace App\Http\Resources;

use App\Support\PromoPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'type_label' => $this->type?->label(),
            'is_sellable' => $this->type?->isSellable() ?? true,
            'category_id' => $this->assignedCategory()?->id,
            // Dua kunci lama dipertahankan supaya frontend tidak putus; isinya
            // kini nama kategori entitas, bukan nilai enum.
            'category' => $this->assignedCategory()?->name,
            'category_label' => $this->assignedCategory()?->name,
            'unit_id' => $this->unit_id,
            // `unit` dipertahankan sebagai label supaya kasir dan daftar
            // produk tidak perlu tahu satuan sudah jadi tabel sendiri.
            // Cadangannya kolom string lama, untuk produk yang belum terpetakan.
            'unit' => $this->unitLabel(),
            'unit_label' => $this->unitLabel(),
            'stock_balance' => $this->stock_balance,
            'min_threshold' => $this->min_threshold,
            'price' => $this->price,
            // Harga promo dihitung di server supaya kasir dan struk memakai
            // angka yang sama persis.
            'promo' => $this->promoPayload($request),
            // Promo yang ada tapi sedang di luar periodenya; tanpa ini harga
            // penuh tampil tanpa penjelasan dan terbaca sebagai salah hitung.
            'promo_dormant' => $this->dormantPromoPayload($request),
            'knowledge' => $this->knowledge,
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'is_low_stock' => $this->is_low_stock,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Nama satuan yang tampil di layar. Relasi lebih dulu; kolom string lama
     * hanya dipakai selama produk itu belum sempat dipetakan.
     *
     * Relasinya diambil lewat getRelationValue, bukan `$this->unit`: selama
     * kolom string bernama sama masih ada, atribut selalu menang atas relasi
     * dan yang terbaca justru teks lamanya.
     */
    private function unitLabel(): ?string
    {
        $unit = $this->resource->getRelationValue('unit');

        return $unit?->name ?? $this->resource->getRawOriginal('unit');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function promoPayload(Request $request): ?array
    {
        $pricing = $request->attributes->get('promo_pricing');

        if (! $pricing instanceof PromoPricing) {
            $pricing = new PromoPricing;
        }

        $promo = $pricing->bestFor($this->resource);

        if ($promo === null) {
            return null;
        }

        return [
            'id' => $promo->id,
            'name' => $promo->name,
            'discount_type' => $promo->discount_type,
            'discount_value' => $promo->discount_value,
            'price' => number_format($pricing->priceFor($this->resource), 2, '.', ''),
        ];
    }

    /**
     * Promo yang terpasang di barang ini tapi sedang tidak berlaku.
     *
     * Kasir yang melihat harga penuh tanpa keterangan menyimpulkan
     * hitungannya salah. Dengan ini ia tahu promonya memang ada, hanya
     * tanggalnya yang belum/sudah lewat — dan bisa mengatakannya ke pasien.
     *
     * @return array<string, mixed>|null
     */
    private function dormantPromoPayload(Request $request): ?array
    {
        $pricing = $request->attributes->get('promo_pricing');

        if (! $pricing instanceof PromoPricing) {
            $pricing = new PromoPricing;
        }

        $promo = $pricing->dormantFor($this->resource);

        if ($promo === null) {
            return null;
        }

        $belumMulai = $promo->starts_at?->greaterThan(Carbon::today()) === true;

        return [
            'id' => $promo->id,
            'name' => $promo->name,
            'state' => $belumMulai ? 'upcoming' : 'ended',
            'starts_at' => $promo->starts_at?->toDateString(),
            'ends_at' => $promo->ends_at?->toDateString(),
        ];
    }
}
