<?php

namespace App\Http\Controllers;

use App\Enums\BroadcastAudience;
use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Http\Requests\BroadcastPreviewRequest;
use App\Http\Requests\BroadcastRecipientStatusRequest;
use App\Http\Requests\BroadcastRequest;
use App\Http\Requests\BroadcastStatusRequest;
use App\Http\Requests\BroadcastTestRequest;
use App\Http\Requests\WhatsappSessionRequest;
use App\Http\Resources\BroadcastRecipientResource;
use App\Http\Resources\BroadcastResource;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use App\Services\BroadcastService;
use App\Support\BroadcastAudienceBuilder;
use App\Support\PhoneNumber;
use App\Support\TenantCache;
use App\Support\WahaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BroadcastController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        $page = Broadcast::query()
            ->with('creator')
            ->withCount(['recipients', 'sentRecipients', 'pendingRecipients', 'failedRecipients'])
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
    public function preview(BroadcastPreviewRequest $request, BroadcastAudienceBuilder $builder): JsonResponse
    {
        $this->authorize('create', Broadcast::class);

        $validated = $request->validated();

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
                $broadcast->loadCount(['recipients', 'sentRecipients', 'pendingRecipients', 'failedRecipients']),
            ),
            'meta' => ['message' => __('broadcast.created')],
        ], 201);
    }

    public function show(Broadcast $broadcast): JsonResponse
    {
        $this->authorize('view', $broadcast);

        $broadcast->load(['creator', 'recipients' => fn ($q) => $q->orderBy('name')])
            ->loadCount(['recipients', 'sentRecipients', 'pendingRecipients', 'failedRecipients']);

        return response()->json([
            'data' => new BroadcastResource($broadcast),
            'meta' => ['waha_ready' => app(WahaClient::class) !== null],
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
        BroadcastRecipientStatusRequest $request,
        Broadcast $broadcast,
        BroadcastRecipient $recipient,
        BroadcastService $broadcasts,
    ): JsonResponse {
        $this->authorize('update', $broadcast);

        abort_unless($recipient->broadcast_id === $broadcast->id, 404);

        $validated = $request->validated();

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
    public function changeStatus(BroadcastStatusRequest $request, Broadcast $broadcast, BroadcastService $broadcasts): JsonResponse
    {
        $this->authorize('update', $broadcast);

        $validated = $request->validated();

        $broadcast = $broadcasts->changeStatus($broadcast, BroadcastStatus::from($validated['status']));

        return response()->json([
            'data' => new BroadcastResource(
                $broadcast->loadCount(['recipients', 'sentRecipients', 'pendingRecipients', 'failedRecipients']),
            ),
            'meta' => [],
        ]);
    }

    /** Kirim pesan uji ke satu nomor sebelum blast sungguhan. */
    public function sendTest(BroadcastTestRequest $request, BroadcastService $broadcasts): JsonResponse
    {
        $this->authorize('create', Broadcast::class);

        $validated = $request->validated();

        $phone = PhoneNumber::normalize($validated['phone']);

        abort_if($phone === null, 422, __('broadcast.invalid_phone'));

        $broadcasts->sendTest($phone, $validated['message']);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('broadcast.test_sent')],
        ]);
    }

    /**
     * Keadaan sesi WAHA klinik ini, plus QR bila belum tersambung.
     *
     * `available` false berarti belum ada yang bisa ditampilkan sama sekali:
     * entah server WAHA-nya belum diisi pengelola platform, atau klinik ini
     * belum menyebut nama sesinya.
     */
    public function connection(): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        $client = app(WahaClient::class);

        if ($client === null) {
            return response()->json([
                'data' => ['available' => false, 'connected' => false, 'qr' => null],
                'meta' => [],
            ]);
        }

        try {
            $status = $client->sessionStatus();
            $statusValue = $status['status'] ?? 'NOT_FOUND';

            if ($statusValue === 'WORKING') {
                return response()->json([
                    'data' => [
                        'available' => true,
                        'connected' => true,
                        'qr' => null,
                        'number' => $this->accountNumber($status),
                        'name' => $status['me']['pushName'] ?? null,
                    ],
                    'meta' => [],
                ]);
            }

            if ($statusValue === 'NOT_FOUND') {
                $client->createSession();
            } elseif ($statusValue === 'FAILED') {
                $client->restartSession();
            } else {
                // STARTING / SCAN_QR_CODE — sesi sudah ada, tinggal dijalankan.
                $client->startSession();
            }

            // null aman kalau masih STARTING dan QR belum terbit.
            $qr = $client->qrCode();
        } catch (\Throwable $e) {
            Log::error('WAHA tidak merespons', ['exception' => $e]);

            return response()->json([
                'data' => ['available' => true, 'connected' => false, 'qr' => null, 'error' => true],
                'meta' => [],
            ]);
        }

        return response()->json([
            'data' => [
                'available' => true,
                'connected' => false,
                'qr' => $qr,
                'number' => null,
                'name' => null,
            ],
            'meta' => [],
        ]);
    }

    /**
     * WAHA menyebut akunnya sebagai chat id (`628xx@c.us`); yang dibaca admin
     * hanya nomornya.
     *
     * @param  array<string, mixed>  $status
     */
    private function accountNumber(array $status): ?string
    {
        $id = $status['me']['id'] ?? null;

        return is_string($id) ? explode('@', $id)[0] : null;
    }

    /** Ringkasan dashboard WhatsApp: koneksi, pesan hari ini, campaign aktif. */
    public function dashboard(): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        // whereDate membungkus kolomnya dengan fungsi tanggal, dan kolom yang
        // dibungkus fungsi tidak bisa dipakai index — pemindaian penuh tabel
        // penerima yang justru paling cepat menggemuk. Rentang antara awal
        // dan akhir hari membandingkan nilai apa adanya, sehingga indexnya
        // terpakai.
        $dayStart = today()->startOfDay();
        $dayEnd = today()->endOfDay();

        // Ditahan sebentar: dashboard ini yang paling sering ditinggal terbuka
        // di layar depan klinik, dan angkanya tidak perlu tepat per detik.
        $counts = TenantCache::remember(
            'broadcast:dashboard',
            TenantCache::TTL_LIVE,
            function () use ($dayStart, $dayEnd): array {
                $today = BroadcastRecipient::query()
                    ->whereBetween('updated_at', [$dayStart, $dayEnd])
                    ->selectRaw('status, COUNT(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

                $reminderToday = BroadcastRecipient::query()
                    ->whereNotNull('reminder_rule_id')
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->selectRaw('status, COUNT(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

                return [
                    'today' => $today->all(),
                    'reminder_today' => $reminderToday->all(),
                    'active_campaigns' => Broadcast::query()
                        ->whereIn('status', [BroadcastStatus::Sending, BroadcastStatus::Paused])
                        ->count(),
                ];
            },
        );

        $today = collect($counts['today']);
        $reminderToday = collect($counts['reminder_today']);

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
                'active_campaigns' => (int) $counts['active_campaigns'],
            ],
            'meta' => [],
        ]);
    }

    public function settings(): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        return response()->json([
            'data' => [
                'session' => WhatsappSetting::query()->first()?->session,
                // Klinik tidak bisa berbuat apa-apa sampai pengelola platform
                // mengisi alamat servernya, jadi keadaannya ikut disebut.
                'waha_available' => WahaSetting::query()->first()?->isConfigured() ?? false,
            ],
            'meta' => [],
        ]);
    }

    public function updateSettings(WhatsappSessionRequest $request): JsonResponse
    {
        // Pemeriksaan di FormRequest bukan pengganti pemeriksaan di sini:
        // yang pertama menjaga bentuk permintaan, yang kedua menjaga aksinya.
        // Keduanya dipasang supaya menghapus salah satunya tidak diam-diam
        // membuka pintu.
        $this->authorize('update', Broadcast::class);

        $validated = $request->validated();

        $setting = WhatsappSetting::query()->firstOrNew([]);
        $setting->session = $validated['session'] ?? null;
        $setting->save();

        return response()->json([
            'data' => ['session' => $setting->session],
            'meta' => ['message' => __('broadcast.settings_saved')],
        ]);
    }
}
