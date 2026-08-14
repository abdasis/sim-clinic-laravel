<?php

namespace App\Http\Resources\CompanyProfile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk tunggal yang dikonsumsi halaman landing. `resource` di sini array
 * hasil `CompanyProfileService::landingData()`, bukan satu model.
 */
class CompanyProfileLandingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'is_published' => $data['is_published'],
            'settings' => $data['settings']
                ? new CompanyProfileSettingResource($data['settings'])
                : null,
            'navigation' => [
                'header' => CompanyNavigationItemResource::collection($data['navigation']['header']),
                'footer' => CompanyNavigationItemResource::collection($data['navigation']['footer']),
            ],
            'slides' => CompanyProfileSlideResource::collection($data['slides']),
            'value_props' => CompanyValuePropResource::collection($data['value_props']),
            'treatments' => CompanyTreatmentResource::collection($data['treatments']),
            'promos' => CompanyPromoResource::collection($data['promos']),
            'brands' => CompanyBrandResource::collection($data['brands']),
            'testimonials' => CompanyTestimonialResource::collection($data['testimonials']),
            // Dipetakan per section_key supaya frontend memilih slot langsung
            // tanpa mencari di dalam array.
            'content_sections' => array_map(
                fn ($section) => new CompanyContentSectionResource($section),
                $data['content_sections'],
            ),
        ];
    }
}
