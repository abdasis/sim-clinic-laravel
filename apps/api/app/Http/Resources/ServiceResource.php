<?php

namespace App\Http\Resources;

use App\Support\PromoPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category_id' => $this->assignedCategory()?->id,
            // Dua kunci lama dipertahankan supaya frontend tidak putus; isinya
            // kini nama kategori entitas, bukan nilai enum.
            'category' => $this->assignedCategory()?->name,
            'category_label' => $this->assignedCategory()?->name,
            'description' => $this->description,
            'knowledge' => $this->knowledge,
            'price' => $this->price,
            'duration_minutes' => $this->duration_minutes,
            // Harga promo dihitung di server supaya kasir dan struk memakai
            // angka yang sama persis.
            'promo' => $this->promoPayload($request),
            // Promo yang ada tapi sedang di luar periodenya; tanpa ini harga
            // penuh tampil tanpa penjelasan dan terbaca sebagai salah hitung.
            'promo_dormant' => $this->dormantPromoPayload($request),
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
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
