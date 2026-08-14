<?php

namespace App\Services;

use App\Enums\CompanyNavPosition;
use App\Models\CompanyBrand;
use App\Models\CompanyContentSection;
use App\Models\CompanyNavigationItem;
use App\Models\CompanyProfileSetting;
use App\Models\CompanyProfileSlide;
use App\Models\CompanyPromo;
use App\Models\CompanyTestimonial;
use App\Models\CompanyTreatment;
use App\Models\CompanyValueProp;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Perakit konten landing publik (spec 010). Landing dibaca pengunjung tanpa
 * login, jadi hanya baris aktif milik tenant yang bersangkutan yang keluar.
 *
 * Semua query di sini read-only — perubahan data lewat Action, seperti
 * ReportService.
 */
class CompanyProfileService
{
    /**
     * Seluruh isi landing dalam satu panggilan. Halaman publik memuat 12
     * section sekaligus; memecahnya jadi belasan request bikin kedip.
     *
     * @return array<string, mixed>
     */
    public function landingData(Tenant $tenant): array
    {
        $settings = CompanyProfileSetting::where('tenant_id', $tenant->id)->first();

        // Sebelum diterbitkan, isi halaman tidak dibagikan sama sekali —
        // draf admin bukan konsumsi publik.
        if (! $settings?->is_published) {
            return [
                'settings' => $settings,
                'is_published' => false,
                'navigation' => ['header' => new Collection, 'footer' => new Collection],
                'slides' => new Collection,
                'value_props' => new Collection,
                'treatments' => new Collection,
                'promos' => new Collection,
                'brands' => new Collection,
                'testimonials' => new Collection,
                'content_sections' => [],
            ];
        }

        // Header dan footer dibaca sekali lalu dipisah di memori; dua query
        // untuk tabel sekecil ini tidak sepadan.
        [$header, $footer] = CompanyNavigationItem::active()->ordered()->get()
            ->partition(fn (CompanyNavigationItem $item) => $item->position === CompanyNavPosition::Header);

        return [
            'settings' => $settings,
            'is_published' => true,
            'navigation' => [
                'header' => $header->values(),
                'footer' => $footer->values(),
            ],
            'slides' => CompanyProfileSlide::active()->ordered()->get(),
            'value_props' => CompanyValueProp::active()->ordered()->get(),
            'treatments' => CompanyTreatment::active()->ordered()->get(),
            'promos' => CompanyPromo::active()->running()->ordered()->get(),
            'brands' => CompanyBrand::active()->ordered()->get(),
            'testimonials' => CompanyTestimonial::active()->ordered()->get(),
            'content_sections' => CompanyContentSection::active()
                ->get()
                ->keyBy(fn (CompanyContentSection $section) => $section->section_key->value)
                ->all(),
        ];
    }

    /**
     * Detail satu treatment untuk halaman publiknya sendiri.
     */
    public function treatmentDetail(string $slug): CompanyTreatment
    {
        $published = CompanyProfileSetting::query()->value('is_published');

        if (! $published) {
            Log::info('Detail treatment diminta saat landing belum diterbitkan.', [
                'slug' => $slug,
            ]);

            abort(404);
        }

        return CompanyTreatment::active()->where('slug', $slug)->firstOrFail();
    }
}
