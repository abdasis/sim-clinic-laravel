<?php

namespace App\Http\Controllers;

use App\Enums\BroadcastAudience;
use App\Enums\BroadcastRecipientStatus;
use App\Http\Requests\BroadcastRequest;
use App\Http\Resources\BroadcastRecipientResource;
use App\Http\Resources\BroadcastResource;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\WhatsappSetting;
use App\Services\BroadcastService;
use App\Support\BroadcastAudienceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BroadcastController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        $page = Broadcast::query()
            ->with('creator')
            ->withCount(['recipients', 'sentRecipients', 'pendingRecipients'])
            ->latest()
            ->paginate(min(50, (int) $request->query('per_page', 15)));

        return response()->json([
            'data' => BroadcastResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * Hitung calon penerima sebelum broadcast dibuat — admin melihat berapa
     * yang terjangkau (dan berapa yang tak bernomor) sebelum menekan kirim.
     */
    public function preview(Request $request, BroadcastAudienceBuilder $builder): JsonResponse
    {
        $this->authorize('create', Broadcast::class);

        $validated = $request->validate([
            'audience' => ['required', Rule::enum(BroadcastAudience::class)],
            'days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'service_id' => ['nullable', 'exists:services,id'],
        ]);

        $built = $builder->build(BroadcastAudience::from($validated['audience']), $validated);

        return response()->json([
            'data' => [
                'count' => $built['recipients']->count(),
                'without_phone' => $built['without_phone'],
                'sample' => $built['recipients']->take(3)->map(fn (array $recipient) => [
                    'name' => $recipient['name'],
                    'phone' => $recipient['phone'],
                ])->values(),
            ],
            'meta' => [],
        ]);
    }

    public function store(BroadcastRequest $request, BroadcastService $broadcasts): JsonResponse
    {
        $this->authorize('create', Broadcast::class);

        $broadcast = $broadcasts->create($request->validated());

        return response()->json([
            'data' => new BroadcastResource(
                $broadcast->loadCount(['recipients', 'sentRecipients', 'pendingRecipients']),
            ),
            'meta' => ['message' => __('broadcast.created')],
        ], 201);
    }

    public function show(Broadcast $broadcast): JsonResponse
    {
        $this->authorize('view', $broadcast);

        $broadcast->load(['creator', 'recipients' => fn ($q) => $q->orderBy('name')])
            ->loadCount(['recipients', 'sentRecipients', 'pendingRecipients']);

        return response()->json([
            'data' => new BroadcastResource($broadcast),
            'meta' => ['gateway_ready' => WhatsappSetting::query()->first()?->isGatewayReady() ?? false],
        ]);
    }

    public function destroy(Broadcast $broadcast, BroadcastService $broadcasts): JsonResponse
    {
        $this->authorize('delete', $broadcast);

        $broadcasts->delete($broadcast);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('broadcast.deleted')],
        ]);
    }

    /**
     * Jalur manual: admin menandai penerima setelah membuka tautan wa.me.
     */
    public function updateRecipient(
        Request $request,
        Broadcast $broadcast,
        BroadcastRecipient $recipient,
        BroadcastService $broadcasts,
    ): JsonResponse {
        $this->authorize('update', $broadcast);

        abort_unless($recipient->broadcast_id === $broadcast->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['sent', 'skipped', 'pending'])],
        ]);

        $recipient = $broadcasts->markRecipient(
            $recipient,
            BroadcastRecipientStatus::from($validated['status']),
        );

        return response()->json([
            'data' => new BroadcastRecipientResource($recipient),
            'meta' => [],
        ]);
    }

    /**
     * Jalur gateway: blast semua penerima pending dari server.
     */
    public function send(Broadcast $broadcast, BroadcastService $broadcasts): JsonResponse
    {
        $this->authorize('update', $broadcast);

        $result = $broadcasts->sendViaGateway($broadcast);

        return response()->json([
            'data' => $result,
            'meta' => ['message' => __('broadcast.sent_summary', $result)],
        ]);
    }

    public function settings(): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        $setting = WhatsappSetting::query()->first();

        return response()->json([
            'data' => [
                'driver' => $setting?->driver?->value ?? 'manual',
                'api_url' => $setting?->api_url,
                // Token tidak pernah dikirim balik; cukup penanda terpasang.
                'has_token' => filled($setting?->api_token),
            ],
            'meta' => [],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $this->authorize('create', Broadcast::class);

        $validated = $request->validate([
            'driver' => ['required', Rule::in(['manual', 'gateway'])],
            'api_url' => ['nullable', 'required_if:driver,gateway', 'url', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:255'],
        ]);

        $setting = WhatsappSetting::query()->firstOrNew([]);
        $setting->driver = $validated['driver'];
        $setting->api_url = $validated['api_url'] ?? null;

        // Token lama dipertahankan bila form mengirim kosong — menyimpan ulang
        // setelan lain tidak boleh diam-diam mencabut kredensial.
        if (filled($validated['api_token'] ?? null)) {
            $setting->api_token = $validated['api_token'];
        }

        $setting->save();

        return response()->json([
            'data' => [
                'driver' => $setting->driver->value,
                'api_url' => $setting->api_url,
                'has_token' => filled($setting->api_token),
            ],
            'meta' => ['message' => __('broadcast.settings_saved')],
        ]);
    }
}
