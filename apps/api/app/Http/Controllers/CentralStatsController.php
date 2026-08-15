<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use Illuminate\Http\JsonResponse;

/**
 * Statistik platform untuk halaman central. Lintas tenant, jadi hanya
 * platform admin yang boleh membacanya — sejalan dengan PlatformTenantController.
 */
class CentralStatsController extends Controller
{
    public function index(StatsService $service): JsonResponse
    {
        if (! auth()->user()->isPlatformAdmin()) {
            abort(403, __('auth.unauthorized'));
        }

        return response()->json($service->central());
    }
}
