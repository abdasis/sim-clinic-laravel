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
            'category' => $this->category,
            'category_label' => $this->category?->label(),
            'unit' => $this->unit,
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
