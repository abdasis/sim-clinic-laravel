<?php

namespace App\Support;

use App\Http\Requests\CompanyProfile\ContentSectionRequest;
use App\Http\Requests\CompanyProfile\FaqRequest;
use App\Http\Requests\CompanyProfile\NavigationItemRequest;
use App\Http\Requests\CompanyProfile\PromoRequest;
use App\Http\Requests\CompanyProfile\SlideRequest;
use App\Http\Requests\CompanyProfile\TestimonialRequest;
use App\Http\Requests\CompanyProfile\TreatmentRequest;
use App\Http\Requests\CompanyProfile\ValuePropRequest;
use App\Http\Resources\CompanyProfile\CompanyContentSectionResource;
use App\Http\Resources\CompanyProfile\CompanyFaqResource;
use App\Http\Resources\CompanyProfile\CompanyNavigationItemResource;
use App\Http\Resources\CompanyProfile\CompanyProfileSlideResource;
use App\Http\Resources\CompanyProfile\CompanyPromoResource;
use App\Http\Resources\CompanyProfile\CompanyTestimonialResource;
use App\Http\Resources\CompanyProfile\CompanyTreatmentResource;
use App\Http\Resources\CompanyProfile\CompanyValuePropResource;
use App\Models\CompanyContentSection;
use App\Models\CompanyFaq;
use App\Models\CompanyNavigationItem;
use App\Models\CompanyProfileSlide;
use App\Models\CompanyPromo;
use App\Models\CompanyTestimonial;
use App\Models\CompanyTreatment;
use App\Models\CompanyValueProp;

/**
 * Tujuh entitas konten company profile diperlakukan sama persis oleh CMS:
 * list, buat, ubah, hapus, aktif/nonaktif, urutkan. Daftar ini menjadikan
 * satu controller dan satu set Action cukup untuk semuanya — menyalin kode
 * yang sama tujuh kali justru lebih rapuh.
 *
 * Menambah entitas konten cukup di sini.
 */
class CompanyContentRegistry
{
    /**
     * slug URL => [model, form request, resource, kolom yang bisa dicari]
     *
     * @var array<string, array{model: class-string, request: class-string, resource: class-string, searchable: array<int, string>}>
     */
    public const ENTITIES = [
        'slides' => [
            'model' => CompanyProfileSlide::class,
            'request' => SlideRequest::class,
            'resource' => CompanyProfileSlideResource::class,
            'searchable' => ['title'],
        ],
        'value-props' => [
            'model' => CompanyValueProp::class,
            'request' => ValuePropRequest::class,
            'resource' => CompanyValuePropResource::class,
            'searchable' => ['title'],
        ],
        'treatments' => [
            'model' => CompanyTreatment::class,
            'request' => TreatmentRequest::class,
            'resource' => CompanyTreatmentResource::class,
            'searchable' => ['title', 'slug'],
        ],
        'promos' => [
            'model' => CompanyPromo::class,
            'request' => PromoRequest::class,
            'resource' => CompanyPromoResource::class,
            'searchable' => ['title'],
        ],
        'testimonials' => [
            'model' => CompanyTestimonial::class,
            'request' => TestimonialRequest::class,
            'resource' => CompanyTestimonialResource::class,
            'searchable' => ['author_name'],
        ],
        'faqs' => [
            'model' => CompanyFaq::class,
            'request' => FaqRequest::class,
            'resource' => CompanyFaqResource::class,
            'searchable' => ['question'],
        ],
        'content-sections' => [
            'model' => CompanyContentSection::class,
            'request' => ContentSectionRequest::class,
            'resource' => CompanyContentSectionResource::class,
            'searchable' => ['section_key'],
        ],
        'navigation-items' => [
            'model' => CompanyNavigationItem::class,
            'request' => NavigationItemRequest::class,
            'resource' => CompanyNavigationItemResource::class,
            'searchable' => ['url'],
        ],
    ];

    /**
     * @return array{model: class-string, request: class-string, resource: class-string, searchable: array<int, string>}
     */
    public static function get(string $entity): array
    {
        return self::ENTITIES[$entity] ?? abort(404);
    }

    /**
     * @return array<int, class-string>
     */
    public static function models(): array
    {
        return array_column(self::ENTITIES, 'model');
    }

    /** Nama entitas untuk narasi audit, mis. "slides" -> "Slide Hero". */
    public static function label(string $entity): string
    {
        return __('company_profile.'.str_replace('-', '_', $entity));
    }
}
