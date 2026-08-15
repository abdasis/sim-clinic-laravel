<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardSummaryRequest;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

/**
 * Dasbor klinik — halaman pertama setelah masuk, dibaca semua peran.
 */
class DashboardController extends Controller
{
    public function summary(DashboardSummaryRequest $request, DashboardService $service): JsonResponse
    {
        return response()->json($service->summary($request->days()));
    }
}
