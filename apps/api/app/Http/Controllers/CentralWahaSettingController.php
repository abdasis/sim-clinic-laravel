<?php

namespace App\Http\Controllers;

use App\Http\Requests\WahaSettingRequest;
use App\Models\WahaSetting;
use Illuminate\Http\JsonResponse;

/**
 * Pengaturan server WAHA untuk seluruh klinik. Lintas tenant, jadi hanya
 * platform admin yang boleh menyentuhnya — sejalan dengan
 * CentralStatsController dan PlatformTenantController.
 */
class CentralWahaSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $this->ensurePlatformAdmin();

        $setting = WahaSetting::query()->first();

        return response()->json([
            'data' => [
                'base_url' => $setting?->base_url,
                // Kuncinya tidak pernah dikirim balik; cukup penanda terpasang.
                'has_api_key' => filled($setting?->api_key),
            ],
            'meta' => [],
        ]);
    }

    public function update(WahaSettingRequest $request): JsonResponse
    {
        $this->ensurePlatformAdmin();

        $setting = WahaSetting::query()->firstOrNew([]);
        $setting->base_url = $request->validated('base_url');

        if (filled($request->validated('api_key'))) {
            $setting->api_key = $request->validated('api_key');
        }

        $setting->save();

        return response()->json([
            'data' => [
                'base_url' => $setting->base_url,
                'has_api_key' => filled($setting->api_key),
            ],
            'meta' => ['message' => __('waha.saved')],
        ]);
    }

    /**
     * Satu alamat server dipakai seluruh klinik, jadi salah sentuh di sini
     * memutus pengiriman semua orang sekaligus. Pemeriksaannya berdiri
     * sendiri di controller, tidak menumpang pada FormRequest.
     */
    private function ensurePlatformAdmin(): void
    {
        if (! auth()->user()?->isPlatformAdmin()) {
            abort(403, __('auth.unauthorized'));
        }
    }
}
