<?php

namespace App\Http\Resources\CompanyProfile;

use App\Http\Resources\CompanyProfile\Concerns\ExposesMedia;
use App\Support\PromoPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTreatmentResource extends JsonResource
{
    use ExposesMedia;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'image_url' => $this->mediaUrl($this->image_path),
            'badge' => $this->badge?->value,
            'badge_label' => $this->badge?->label(),
            'category_tags' => $this->category_tags ?? [],
            'detail_url' => $this->detail_url,
            'service_id' => $this->service_id,
            'sort_order' => $this->sort_order,
            // Harga dan durasi tidak disalin ke tabel konten: yang berlaku
            // adalah katalog layanan, dan menyalinnya berarti dua angka yang
            // cepat atau lambat berbeda.
            'price' => $this->whenLoaded('service', fn () => $this->service?->price),
            'duration_minutes' => $this->whenLoaded('service', fn () => $this->service?->duration_minutes),
            'promo' => $this->whenLoaded('service', fn () => $this->promoPayload()),
            'faqs' => CompanyFaqResource::collection($this->whenLoaded('faqs')),
        ];
    }

    /**
     * Harga promo yang sedang berlaku untuk layanannya, bila ada.
     *
     * Dihitung dari sumber yang sama dengan kasir dan nota, supaya angka di
     * halaman publik tidak pernah berbeda dari yang ditagih di meja depan.
     *
     * @return array<string, mixed>|null
     */
    private function promoPayload(): ?array
    {
        $service = $this->service;

        if ($service === null) {
            return null;
        }

        $pricing = new PromoPricing;
        $promo = $pricing->bestFor($service);

        if ($promo === null) {
            return null;
        }

        return [
            'name' => $promo->name,
            'price' => number_format($pricing->priceFor($service), 2, '.', ''),
        ];
    }
}
