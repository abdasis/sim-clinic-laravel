<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatsModuleRequest;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;

/**
 * Ringkasan statistik per modul untuk kepala halaman index admin.
 *
 * Statistik tidak punya Eloquent model sendiri, jadi otorisasinya memeriksa
 * permission spatie langsung — sama seperti ReportController. Yang dipakai
 * `{module}.view`: kalau seseorang boleh melihat daftarnya, dia juga boleh
 * melihat ringkasannya.
 */
class StatsController extends Controller
{
    public function show(StatsModuleRequest $request, StatsService $service): JsonResponse
    {
        $module = $request->module();

        if (! $request->user()->can($module->permission())) {
            abort(403, __('clinic.forbidden'));
        }

        return response()->json($service->forModule($module, $request->days()));
    }
}
