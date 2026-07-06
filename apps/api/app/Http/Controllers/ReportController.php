<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRangeRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

/**
 * ReportController — laporan omzet, treatment, produk (US8, kontrak §8).
 *
 * Reports tidak punya Eloquent model, jadi authorize via Gate clinic.access langsung.
 * Admin only (FR-075). Rentang tanpa data -> data kosong + meta.empty=true (FR-074).
 */
class ReportController extends Controller
{
    private function authorizeReport(): void
    {
        if (! auth()->user()->can('clinic.access', ['report', 'r'])) {
            abort(403, __('clinic.forbidden'));
        }
    }

    public function revenue(ReportRangeRequest $request, ReportService $service): JsonResponse
    {
        $this->authorizeReport();

        $data = $service->revenue($request->validated('from'), $request->validated('to'));

        $meta = [];
        if ($data['paid_transactions_count'] === 0) {
            $meta['empty'] = true;
        }

        return response()->json(['data' => $data, 'meta' => $meta]);
    }

    public function services(ReportRangeRequest $request, ReportService $service): JsonResponse
    {
        $this->authorizeReport();

        $rows = $service->servicesReport($request->validated('from'), $request->validated('to'));

        return response()->json([
            'data' => $rows,
            'meta' => ['empty' => count($rows) === 0],
        ]);
    }

    public function products(ReportRangeRequest $request, ReportService $service): JsonResponse
    {
        $this->authorizeReport();

        $rows = $service->productsReport($request->validated('from'), $request->validated('to'));

        return response()->json([
            'data' => $rows,
            'meta' => ['empty' => count($rows) === 0],
        ]);
    }
}
