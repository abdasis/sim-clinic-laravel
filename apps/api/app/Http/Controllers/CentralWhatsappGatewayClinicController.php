<?php

namespace App\Http\Controllers;

use App\Actions\Whatsapp\RegisterGatewayWebhookAction;
use App\Actions\Whatsapp\SaveWhatsappClinicSettingAction;
use App\Http\Requests\WhatsappClinicSettingRequest;
use App\Models\Tenant;
use App\Models\WhatsappSetting;
use App\Support\PublicUrl;
use App\Support\WhatsappWebhookUrl;
use Illuminate\Http\JsonResponse;

/**
 * Setelan gateway WhatsApp per klinik dari dashboard central. Kredensial
 * hanya ditulis dan dilaporkan sebagai status; nilainya tidak pernah keluar
 * dari server.
 */
class CentralWhatsappGatewayClinicController extends Controller
{
    public function index(): JsonResponse
    {
        $this->ensurePlatformAdmin();

        $tenants = Tenant::query()
            ->where('slug', '!=', 'central')
            ->orderBy('name')
            ->get();

        // Satu kueri untuk semua setelan, bukan satu per klinik.
        $settings = WhatsappSetting::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenants->pluck('id'))
            ->get()
            ->keyBy('tenant_id');

        return response()->json([
            'data' => $tenants->map(
                fn (Tenant $tenant): array => $this->present($tenant, $settings->get($tenant->id)),
            ),
            'meta' => [],
        ]);
    }

    public function update(
        WhatsappClinicSettingRequest $request,
        Tenant $tenant,
        SaveWhatsappClinicSettingAction $action,
    ): JsonResponse {
        $this->ensurePlatformAdmin();

        $setting = $action->handle($tenant, $request->validated());

        return response()->json([
            'data' => $this->present($tenant, $setting),
            'meta' => ['message' => __('whatsapp_gateway.clinic_saved')],
        ]);
    }

    public function webhook(Tenant $tenant, RegisterGatewayWebhookAction $action): JsonResponse
    {
        $this->ensurePlatformAdmin();

        $setting = WhatsappSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($setting === null || blank($setting->gateway_api_key)) {
            abort(422, __('whatsapp_gateway.api_key_required'));
        }

        // Gateway tidak bisa memanggil alamat lokal; memanggilnya untuk alamat
        // itu hanya membuang waktu dan menyamarkan penyebabnya.
        if (! PublicUrl::isGatewayReachable((string) config('app.url'))) {
            abort(422, __('whatsapp_gateway.public_url_required'));
        }

        // false berarti webhook sudah terdaftar: rahasianya tersimpan dan
        // pembuatan ulang hanya akan menggandakan webhook.
        $action->handle($setting);

        return response()->json([
            'data' => $this->present($tenant, $setting->refresh()),
            'meta' => ['message' => __('whatsapp_gateway.webhook_registered')],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Tenant $tenant, ?WhatsappSetting $setting): array
    {
        return [
            'tenant_id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'session' => $setting?->session,
            'daily_message_limit' => $setting?->daily_message_limit,
            'api_key_configured' => filled($setting?->gateway_api_key),
            'webhook_configured' => filled($setting?->gateway_webhook_secret)
                && filled($setting?->gateway_webhook_token),
            'webhook_token_set' => filled($setting?->gateway_webhook_token),
            'webhook_url' => WhatsappWebhookUrl::for($setting?->gateway_webhook_token),
        ];
    }

    /**
     * Setelan ini lintas tenant, jadi hanya pengelola platform yang boleh
     * melihat maupun mengubahnya.
     */
    private function ensurePlatformAdmin(): void
    {
        if (! auth()->user()?->isPlatformAdmin()) {
            abort(403, __('auth.unauthorized'));
        }
    }
}
