<?php

namespace App\Http\Controllers;

use App\Enums\BroadcastAudience;
use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Http\Requests\BroadcastRequest;
use App\Http\Resources\BroadcastRecipientResource;
use App\Http\Resources\BroadcastResource;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\WhatsappSetting;
use App\Services\BroadcastService;
use App\Support\BroadcastAudienceBuilder;
use App\Support\PhoneNumber;
use App\Support\WhatsappClientFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            'meta' => ['gateway_ready' => app(WhatsappClientFactory::class)->forCurrentTenant() !== null],
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
     * Antrekan blast: HTTP kembali seketika, worker yang mengirim dengan
     * jeda antar pesan.
     */
    public function send(Broadcast $broadcast, BroadcastService $broadcasts): JsonResponse
    {
        $this->authorize('update', $broadcast);

        $result = $broadcasts->queueSend($broadcast);

        return response()->json([
            'data' => $result,
            'meta' => ['message' => __('broadcast.queued_summary', $result)],
        ]);
    }

    /** Jeda / lanjut / batalkan campaign yang sedang berjalan. */
    public function changeStatus(Request $request, Broadcast $broadcast, BroadcastService $broadcasts): JsonResponse
    {
        $this->authorize('update', $broadcast);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['sending', 'paused', 'cancelled'])],
        ]);

        $broadcast = $broadcasts->changeStatus($broadcast, BroadcastStatus::from($validated['status']));

        return response()->json([
            'data' => new BroadcastResource(
                $broadcast->loadCount(['recipients', 'sentRecipients', 'pendingRecipients']),
            ),
            'meta' => [],
        ]);
    }

    /** Kirim pesan uji ke satu nomor sebelum blast sungguhan. */
    public function sendTest(Request $request, BroadcastService $broadcasts): JsonResponse
    {
        $this->authorize('create', Broadcast::class);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $phone = PhoneNumber::normalize($validated['phone']);

        abort_if($phone === null, 422, __('broadcast.invalid_phone'));

        $broadcasts->sendTest($phone, $validated['message']);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('broadcast.test_sent')],
        ]);
    }

    /**
     * Status koneksi sidecar QR: terhubung/tidak plus QR untuk discan.
     * Diproksikan lewat backend supaya token sidecar tidak sampai ke browser.
     */
    public function connection(): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        if (! WhatsappClientFactory::sidecarConfigured()) {
            return response()->json([
                'data' => ['available' => false, 'connected' => false, 'qr' => null],
                'meta' => [],
            ]);
        }

        try {
            $base = rtrim((string) config('services.wa_sidecar.url'), '/');
            $token = (string) config('services.wa_sidecar.token');

            $status = Http::timeout(8)->withHeaders(['Authorization' => $token])
                ->get($base.'/status')->throw()->json();

            $qr = null;

            if (! ($status['connected'] ?? false)) {
                $qr = Http::timeout(8)->withHeaders(['Authorization' => $token])
                    ->get($base.'/qr')->json('qr');
            }

            return response()->json([
                'data' => [
                    'available' => true,
                    'connected' => (bool) ($status['connected'] ?? false),
                    'number' => $status['number'] ?? null,
                    'connected_at' => $status['connected_at'] ?? null,
                    'qr' => $qr,
                ],
                'meta' => [],
            ]);
        } catch (\Throwable $e) {
            Log::error('Sidecar WhatsApp tidak merespons', ['exception' => $e]);

            return response()->json([
                'data' => ['available' => true, 'connected' => false, 'qr' => null, 'error' => true],
                'meta' => [],
            ]);
        }
    }

    /** Ringkasan dashboard WhatsApp: koneksi, pesan hari ini, campaign aktif. */
    public function dashboard(): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        $today = BroadcastRecipient::query()
            ->whereDate('updated_at', today())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $reminderToday = BroadcastRecipient::query()
            ->whereNotNull('reminder_rule_id')
            ->whereDate('created_at', today())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'data' => [
                'today' => [
                    'sent' => (int) ($today['sent'] ?? 0),
                    'failed' => (int) ($today['failed'] ?? 0),
                    'pending' => (int) ($today['pending'] ?? 0),
                ],
                'reminders_today' => [
                    'total' => (int) $reminderToday->sum(),
                    'sent' => (int) ($reminderToday['sent'] ?? 0),
                    'failed' => (int) ($reminderToday['failed'] ?? 0),
                ],
                'active_campaigns' => Broadcast::query()
                    ->whereIn('status', [BroadcastStatus::Sending, BroadcastStatus::Paused])
                    ->count(),
            ],
            'meta' => [],
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
                'sidecar_available' => WhatsappClientFactory::sidecarConfigured(),
            ],
            'meta' => [],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $this->authorize('create', Broadcast::class);

        $validated = $request->validate([
            'driver' => ['required', Rule::in(['manual', 'gateway', 'qr'])],
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
