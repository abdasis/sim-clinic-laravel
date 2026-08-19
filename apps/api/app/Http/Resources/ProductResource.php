<?php

namespace App\Http\Resources;

use App\Support\PromoPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
}
