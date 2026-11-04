<?php

namespace App\Http\Controllers;

use App\Http\Requests\WhatsappGatewaySettingRequest;
use App\Models\WhatsappGatewaySetting;
use Illuminate\Http\JsonResponse;

/**
 * Alamat server gateway WhatsApp internal untuk seluruh klinik. Lintas tenant,
 * jadi hanya platform admin yang boleh menyentuhnya — sejalan dengan
 * CentralStatsController dan PlatformTenantController.
 */
class CentralWhatsappGatewaySettingController extends Controller
{
    public function show(): JsonResponse
    {
        $this->ensurePlatformAdmin();

        $setting = WhatsappGatewaySetting::query()->first();

        return response()->json([
            'data' => ['base_url' => $setting?->base_url],
            'meta' => [],
        ]);
    }

    public function update(WhatsappGatewaySettingRequest $request): JsonResponse
    {
        $this->ensurePlatformAdmin();

        $setting = WhatsappGatewaySetting::query()->firstOrNew([]);
        $setting->base_url = $request->validated('base_url');
        $setting->save();

        return response()->json([
            'data' => ['base_url' => $setting->base_url],
            'meta' => ['message' => __('whatsapp_gateway.saved')],
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
