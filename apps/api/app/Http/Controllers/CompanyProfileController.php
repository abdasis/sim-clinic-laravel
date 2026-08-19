<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyProfile\CompanyProfileChromeResource;
use App\Http\Resources\CompanyProfile\CompanyProfileLandingResource;
use App\Http\Resources\CompanyProfile\CompanyTreatmentResource;
use App\Models\CompanyProfileSetting;
use App\Models\Tenant;
use App\Services\CompanyProfileService;
use App\Support\LocaleText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Landing publik company profile — dibaca pengunjung tanpa login.
 */
class CompanyProfileController extends Controller
{
    public function index(Request $request, CompanyProfileService $service): JsonResponse
    {
        $tenant = app('tenant');
        $data = $service->landingData($tenant);

        return response()->json([
            'data' => new CompanyProfileLandingResource($data),
            'meta' => [
                'locale' => $this->locale($request, $data['settings']),
                'tenant' => $this->tenantSummary($tenant),
            ],
        ]);
    }

    public function showTreatment(Request $request, string $slug, CompanyProfileService $service): JsonResponse
    {
        $treatment = $service->treatmentDetail($slug);

        return response()->json([
            'data' => new CompanyTreatmentResource($treatment),
            'meta' => [
                // Halaman buntu membuat pengunjung menutup tab; tiga saran
                // terdekat memberinya langkah berikutnya.
                'related' => CompanyTreatmentResource::collection(
                    $service->relatedTreatments($treatment),
                ),
                // Kop dan kaki halaman ikut dikirim: halaman detail sering
                // jadi pintu masuk pertama dari pencarian, dan pengunjung
                // yang mendarat tanpa menu tidak punya jalan ke mana pun.
                'chrome' => new CompanyProfileChromeResource(
                    $service->landingData(app('tenant')),
                ),
                'locale' => $this->locale($request, null),
                'tenant' => $this->tenantSummary(app('tenant')),
            ],
        ]);
    }

    /**
     * Bahasa yang diminta pengunjung; jatuh ke default tenant lalu ke `id`.
     * Konten dikirim sebagai peta bahasa, jadi ini sekadar petunjuk render.
     */
    private function locale(Request $request, ?CompanyProfileSetting $settings): string
    {
        $requested = $request->query('locale');

        if (in_array($requested, LocaleText::SUPPORTED, true)) {
            return $requested;
        }

        return $settings?->default_locale ?? LocaleText::FALLBACK;
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantSummary(Tenant $tenant): array
    {
        return ['slug' => $tenant->slug, 'name' => $tenant->name];
    }
}
