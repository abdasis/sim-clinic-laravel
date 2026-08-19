<?php

namespace App\Services;

use App\Actions\CompanyProfile\CreateCompanyContentAction;
use App\Actions\CompanyProfile\DeleteCompanyContentAction;
use App\Actions\CompanyProfile\ReorderCompanyContentAction;
use App\Actions\CompanyProfile\ToggleCompanyContentActiveAction;
use App\Actions\CompanyProfile\UpdateCompanyContentAction;
use App\Actions\CompanyProfile\UpdateCompanyProfileSettingsAction;
use App\Actions\CompanyProfile\UploadCompanyMediaAction;
use App\Enums\CompanyNavPosition;
use App\Models\CompanyContentSection;
use App\Models\CompanyFaq;
use App\Models\CompanyNavigationItem;
use App\Models\CompanyProfileSetting;
use App\Models\CompanyProfileSlide;
use App\Models\CompanyPromo;
use App\Models\CompanyTestimonial;
use App\Models\CompanyTreatment;
use App\Models\CompanyValueProp;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
                'testimonials' => new Collection,
                'faqs' => new Collection,
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
            'promos' => CompanyPromo::active()->ordered()->get(),
            'testimonials' => CompanyTestimonial::active()->ordered()->get(),
            // Hanya pertanyaan umum: yang menempel ke satu treatment milik
            // halaman treatment itu, bukan beranda.
            'faqs' => CompanyFaq::active()->whereNull('company_treatment_id')->ordered()->get(),
            'content_sections' => CompanyContentSection::active()
                ->get()
                ->keyBy(fn (CompanyContentSection $section) => $section->section_key->value)
                ->all(),
        ];
    }

    /**
     * Treatment lain yang layak ditawarkan setelah membaca satu treatment.
     *
     * Kedekatannya diukur dari kesamaan tag: pengunjung yang membaca laser
     * penghilang bulu lebih mungkin tertarik pada laser lain daripada pada
     * facial yang kebetulan berurutan di daftar. Bila tidak ada tag yang
     * cocok, yang tampil treatment teratas — halaman buntu lebih buruk
     * daripada saran yang kurang tepat.
     *
     * @return Collection<int, CompanyTreatment>
     */
    public function relatedTreatments(CompanyTreatment $treatment, int $limit = 3): Collection
    {
        $tags = array_map('mb_strtolower', $treatment->category_tags ?? []);

        $candidates = CompanyTreatment::active()
            ->where('id', '!=', $treatment->id)
            ->ordered()
            ->get();

        $ranked = $candidates
            ->sortByDesc(fn (CompanyTreatment $other) => count(array_intersect(
                $tags,
                array_map('mb_strtolower', $other->category_tags ?? []),
            )))
            ->values();

        return $ranked->take($limit);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $data
     */
    public function create(string $modelClass, string $entity, array $data): Model
    {
        return app(CreateCompanyContentAction::class)->handle($modelClass, $entity, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model $record, string $entity, array $data): Model
    {
        return app(UpdateCompanyContentAction::class)->handle($record, $entity, $data);
    }

    public function delete(Model $record, string $entity): void
    {
        app(DeleteCompanyContentAction::class)->handle($record, $entity);
    }

    public function toggleActive(Model $record, string $entity): Model
    {
        return app(ToggleCompanyContentActiveAction::class)->handle($record, $entity);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int>  $ids
     */
    public function reorder(string $modelClass, string $entity, array $ids): void
    {
        // Urutan hanya bermakna sebagai satu kesatuan; gagal di tengah akan
        // meninggalkan daftar setengah tertata.
        DB::transaction(fn () => app(ReorderCompanyContentAction::class)
            ->handle($modelClass, $entity, $ids));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(Tenant $tenant, array $data): CompanyProfileSetting
    {
        return app(UpdateCompanyProfileSettingsAction::class)->handle($tenant->id, $data);
    }

    public function togglePublish(Tenant $tenant): CompanyProfileSetting
    {
        $current = CompanyProfileSetting::query()
            ->where('tenant_id', $tenant->id)
            ->value('is_published');

        return app(UpdateCompanyProfileSettingsAction::class)
            ->handle($tenant->id, ['is_published' => ! $current]);
    }

    public function uploadMedia(Tenant $tenant, string $entity, UploadedFile $file): string
    {
        return app(UploadCompanyMediaAction::class)->handle($tenant->id, $entity, $file);
    }

    /**
     * Detail satu treatment untuk halaman publiknya sendiri.
     */
    /**
     * Identitas klinik untuk kop nota dan brand sidebar.
     *
     * Tidak mengembalikan CompanyProfileSetting melainkan Tenant-nya, karena
     * nama dan telepon punya cadangan di tabel tenant — klinik yang belum
     * pernah menyentuh halaman profil tetap punya identitas yang bisa
     * ditampilkan. Perakitannya sendiri milik ClinicIdentityResource.
     */
    public function identity(Tenant $tenant): Tenant
    {
        return $tenant->loadMissing('companyProfile');
    }

    public function treatmentDetail(string $slug): CompanyTreatment
    {
        $published = CompanyProfileSetting::query()->value('is_published');

        if (! $published) {
            Log::info('Detail treatment diminta saat landing belum diterbitkan.', [
                'slug' => $slug,
            ]);

            abort(404);
        }

        return CompanyTreatment::active()
            ->with(['service', 'faqs' => fn ($query) => $query->active()->ordered()])
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
