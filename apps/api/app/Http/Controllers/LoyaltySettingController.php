<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoyaltySettingRequest;
use App\Models\LoyaltySetting;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;

/**
 * Tarif poin loyalitas klinik ini.
 *
 * Dibaca juga oleh layar kasir, bukan cuma halaman setelan: perkiraan poin
 * dan nilai penukaran yang muncul sebelum nota terbit harus memakai tarif
 * yang sama dengan yang dipakai server saat menyimpannya.
 */
class LoyaltySettingController extends Controller
{
    public function show(): JsonResponse
    {
        $this->authorize('viewAny', LoyaltySetting::class);

        return response()->json([
            'data' => LoyaltySetting::current(),
            'meta' => [],
        ]);
    }

    public function update(LoyaltySettingRequest $request, LoyaltyService $loyalty): JsonResponse
    {
        $this->authorize('update', LoyaltySetting::class);

        $loyalty->saveSetting($request->validated());

        return response()->json([
            'data' => LoyaltySetting::current(),
            'meta' => ['message' => __('loyalty.setting_saved')],
        ]);
    }
}
