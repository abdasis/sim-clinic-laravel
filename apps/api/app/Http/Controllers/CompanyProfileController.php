<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyProfile\CompanyProfileLandingResource;
use App\Http\Resources\CompanyProfile\CompanyTreatmentResource;
use App\Models\Tenant;
use App\Services\CompanyProfileService;
use Illuminate\Http\JsonResponse;

/**
 * Landing publik company profile — dibaca pengunjung tanpa login.
 */
class CompanyProfileController extends Controller
{
    public function index(CompanyProfileService $service): JsonResponse
    {
        $tenant = app('tenant');

        return response()->json([
            'data' => new CompanyProfileLandingResource($service->landingData($tenant)),
            'meta' => ['tenant' => $this->tenantSummary($tenant)],
        ]);
    }

    public function showTreatment(string $slug, CompanyProfileService $service): JsonResponse
    {
        return response()->json([
            'data' => new CompanyTreatmentResource($service->treatmentDetail($slug)),
            'meta' => ['tenant' => $this->tenantSummary(app('tenant'))],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantSummary(Tenant $tenant): array
    {
        return ['slug' => $tenant->slug, 'name' => $tenant->name];
    }
}
