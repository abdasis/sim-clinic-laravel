<?php

namespace App\Http\Controllers;

use App\Exports\MonthlyReportExport;
use App\Http\Requests\ReportRangeRequest;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ReportController — laporan omzet, treatment, produk (US8, kontrak §8).
 *
 * Reports tidak punya Eloquent model, jadi authorize via permission spatie langsung.
 * Admin only (FR-075). Rentang tanpa data -> data kosong + meta.empty=true (FR-074).
 */
class ReportController extends Controller
{
    private function authorizeReport(): void
    {
        if (! auth()->user()->can('report.view')) {
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

    public function monthly(ReportRangeRequest $request, ReportService $service): JsonResponse
    {
        $this->authorizeReport();

        $data = $service->monthly($request->validated('from'), $request->validated('to'));

        return response()->json([
            'data' => $data,
            'meta' => ['empty' => count($data['rows']) === 0],
        ]);
    }

    /**
     * Unduh laporan bulanan sebagai berkas. Format ditentukan query `format`
     * (xlsx | pdf); nama berkas memuat periode supaya arsipnya tertata.
     */
    public function monthlyExport(ReportRangeRequest $request, ReportService $service): Response
    {
        $this->authorizeReport();

        $from = $request->validated('from');
        $to = $request->validated('to');
        $format = $request->query('format', 'xlsx');
        $report = $service->monthly($from, $to);
        $clinicName = app('tenant')->name;
        $filename = 'laporan-bulanan-'.$from.'-sd-'.$to;

        if ($format === 'pdf') {
            return Pdf::loadView('reports.monthly', [
                'report' => $report,
                'clinicName' => $clinicName,
            ])->setPaper('a4', 'landscape')->download($filename.'.pdf');
        }

        $spreadsheet = (new MonthlyReportExport($report, $clinicName))->build();

        return new StreamedResponse(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
        ]);
    }
}
