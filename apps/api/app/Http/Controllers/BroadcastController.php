<?php

namespace App\Http\Controllers;

use App\Actions\LogAuditAction;
use App\Actions\Whatsapp\SaveWhatsappClinicSettingAction;
use App\Enums\BroadcastAudience;
use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Http\Requests\BroadcastPreviewRequest;
use App\Http\Requests\BroadcastRecipientStatusRequest;
use App\Http\Requests\BroadcastRequest;
use App\Http\Requests\BroadcastStatusRequest;
use App\Http\Requests\BroadcastTestRequest;
use App\Http\Requests\BroadcastUpdateRequest;
use App\Http\Requests\WhatsappPairingRequest;
use App\Http\Requests\WhatsappSessionRequest;
use App\Http\Resources\BroadcastRecipientResource;
use App\Http\Resources\BroadcastResource;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Tenant;
use App\Models\WhatsappGatewaySetting;
use App\Models\WhatsappSetting;
use App\Services\BroadcastService;
use App\Services\WhatsappGatewaySessionService;
use App\Support\BroadcastAudienceBuilder;
use App\Support\DailyMessageQuota;
use App\Support\PhoneNumber;
use App\Support\TenantCache;
use App\Support\WhatsappGatewayException;
use App\Support\WhatsappWebhookUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

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
                'opted_out' => $built['opted_out'],
                // Seluruh nama, bukan tiga contoh. Sebelumnya hanya tiga yang
                // dikirim — dan layarnya bahkan tidak menampilkannya, jadi
                // admin menekan kirim ke ratusan orang tanpa pernah melihat
                // satu pun nama yang akan menerimanya.
                'recipients' => $built['recipients']->map(fn (array $recipient) => [
                    'patient_id' => $recipient['patient_id'],
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

    /** Sunting broadcast selama belum mulai dikirim. */
    public function update(BroadcastUpdateRequest $request, Broadcast $broadcast, BroadcastService $broadcasts): JsonResponse
    {
        $this->authorize('update', $broadcast);

        $broadcast = $broadcasts->update($broadcast, $request->validated());

        return response()->json([
            'data' => new BroadcastResource(
                $broadcast->loadCount(['recipients', 'sentRecipients', 'pendingRecipients', 'failedRecipients']),
            ),
            'meta' => ['message' => __('broadcast.edit_saved')],
        ]);
    }

    public function show(Broadcast $broadcast, BroadcastService $broadcasts): JsonResponse
    {
        $this->authorize('view', $broadcast);

        $broadcast->load(['creator', 'recipients' => fn ($q) => $q->orderBy('name')])
            ->loadCount(['recipients', 'sentRecipients', 'pendingRecipients', 'failedRecipients']);

        // Sekali baca untuk dua kunci: jatahnya bisa habis di tengah blast,
        // dan tombol kirim di layar detail perlu tahu sebelum ditekan.
        $quota = DailyMessageQuota::today();

        return response()->json([
            'data' => new BroadcastResource($broadcast),
            // Pesan bisa disusun tanpa nomor pengirim, tapi kirim tidak.
            'meta' => [
                'gateway_ready' => $broadcasts->gatewayReady(),
                'quota' => $quota->toArray(),
                'quota_spent' => $quota->isSpent(),
            ],
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

        $broadcasts->sendTest($phone, $validated['template']);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('broadcast.test_sent')],
        ]);
    }

    /**
     * Keadaan sesi gateway klinik ini, plus QR bila belum tersambung.
     *
     * Hanya membaca. Layar Koneksi menanyakannya ulang tiap 4 detik supaya QR
     * yang kedaluwarsa berganti sendiri; menyalakan sesi dari sini berarti
     * memulai ulang sesinya belasan kali semenit, dan nomornya tidak pernah
     * sempat tersambung.
     */
    public function connection(WhatsappGatewaySessionService $sessions): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        return response()->json(['data' => $sessions->state(), 'meta' => []]);
    }

    /** Nyalakan sesinya — tindakan tersendiri, diminta admin sekali. */
    public function prepareConnection(WhatsappGatewaySessionService $sessions): JsonResponse
    {
        // Menyalakan sesi mengubah keadaan gateway, bukan sekadar membaca
        // seperti `connection`. Peran yang hanya boleh melihat tidak boleh
        // memicunya.
        $this->authorize('update', Broadcast::class);

        return response()->json(['data' => $sessions->prepare(), 'meta' => []]);
    }

    /**
     * Kode pairing untuk menyambungkan nomor tanpa memindai QR. Hanya berguna
     * saat sesi belum terhubung; gateway menolaknya bila sudah.
     */
    public function pairing(WhatsappPairingRequest $request, WhatsappGatewaySessionService $sessions): JsonResponse
    {
        $this->authorize('update', Broadcast::class);

        return $this->connectionAction(
            'pairing',
            fn (): array => $sessions->pairing($request->validated()['phone']),
            202,
        );
    }

    public function restartConnection(WhatsappGatewaySessionService $sessions): JsonResponse
    {
        $this->authorize('update', Broadcast::class);

        return $this->connectionAction('restart', fn () => $sessions->restart());
    }

    public function disconnectConnection(WhatsappGatewaySessionService $sessions): JsonResponse
    {
        $this->authorize('update', Broadcast::class);

        return $this->connectionAction('disconnect', fn () => $sessions->disconnect());
    }

    public function logoutConnection(WhatsappGatewaySessionService $sessions): JsonResponse
    {
        $this->authorize('update', Broadcast::class);

        return $this->connectionAction('logout', fn () => $sessions->logout());
    }

    /**
     * Hapus sesi gateway klinik. Nama sesinya tetap disimpan supaya bisa
     * disambungkan ulang tanpa mengisi pengaturan lagi.
     */
    public function deleteConnection(WhatsappGatewaySessionService $sessions): JsonResponse
    {
        $this->authorize('update', Broadcast::class);

        try {
            $result = $sessions->delete();
        } catch (Throwable $e) {
            return $this->connectionFailure('delete', $e);
        }

        return response()->json([
            'data' => $result['state'],
            'meta' => [
                'message' => $result['deleted']
                    ? __('broadcast.session_deleted')
                    : __('broadcast.session_none'),
            ],
        ]);
    }

    /**
     * Jalankan aksi sesi. Hanya penolakan gateway yang pesannya bisa dibaca
     * admin — itu pun diteruskan dengan status aslinya (409/422/429). Galat
     * lain bisa membocorkan detail internal, jadi diganti pesan umum.
     *
     * @param  callable(): mixed  $action
     */
    private function connectionAction(string $name, callable $action, int $status = 200): JsonResponse
    {
        try {
            $data = $action();
        } catch (Throwable $e) {
            return $this->connectionFailure($name, $e);
        }

        return response()->json(['data' => $data, 'meta' => []], $status);
    }

    /**
     * Balasan galat aksi sesi. Hanya penolakan gateway yang pesannya bisa
     * dibaca admin; galat lain bisa membocorkan detail internal.
     */
    private function connectionFailure(string $name, Throwable $e): JsonResponse
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        Log::error('Aksi sesi gateway WhatsApp klinik gagal.', [
            'exception' => $e,
            'tenant_id' => $tenant?->id,
            'action' => $name,
        ]);

        if ($e instanceof WhatsappGatewayException) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->status > 0 ? $e->status : 502,
            );
        }

        return response()->json(['message' => __('broadcast.gateway_error')], 502);
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
                // Jatah harian ikut dilaporkan supaya admin tahu sebelum
                // menekan kirim, bukan setelah campaign-nya berhenti sendiri
                // separuh jalan.
                'quota' => DailyMessageQuota::today()->toArray(),
            ],
            'meta' => [],
        ]);
    }

    public function settings(): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        $setting = WhatsappSetting::query()->first();
        $gateway = WhatsappGatewaySetting::query()->first();

        return response()->json([
            'data' => [
                'session' => $setting?->session,
                'daily_message_limit' => $setting?->daily_message_limit,
                // Klinik tidak bisa berbuat apa-apa sampai pengelola platform
                // mengisi alamat servernya, jadi keadaannya ikut disebut.
                'gateway_available' => $gateway?->isConfigured() ?? false,
                'gateway_base_url' => $gateway?->base_url,
                // Hanya statusnya, bukan nilainya: kredensial membuka nomor
                // WhatsApp klinik dan tidak pernah dikirim balik ke aplikasi web.
                'api_key_configured' => filled($setting?->gateway_api_key),
                'webhook_configured' => filled($setting?->gateway_webhook_secret)
                    && filled($setting?->gateway_webhook_token),
                'webhook_url' => WhatsappWebhookUrl::for($setting?->gateway_webhook_token),
            ],
            'meta' => [],
        ]);
    }

    public function updateSettings(
        WhatsappSessionRequest $request,
        SaveWhatsappClinicSettingAction $saveSetting,
    ): JsonResponse {
        // Pemeriksaan di FormRequest bukan pengganti pemeriksaan di sini:
        // yang pertama menjaga bentuk permintaan, yang kedua menjaga aksinya.
        // Keduanya dipasang supaya menghapus salah satunya tidak diam-diam
        // membuka pintu.
        $this->authorize('update', Broadcast::class);

        $validated = $request->validated();
        $tenant = app('tenant');

        // Sesi, batas harian, dan API key milik klinik ini disimpan lewat
        // action yang sama dengan dashboard central, supaya aturan "kosong
        // berarti tidak berubah" tidak ditulis dua kali.
        $setting = $saveSetting->handle($tenant, $validated);

        if (array_key_exists('base_url', $validated)) {
            $this->saveGatewayBaseUrl($validated['base_url'], $tenant);
        }

        $gateway = WhatsappGatewaySetting::query()->first();

        return response()->json([
            'data' => [
                'session' => $setting->session,
                'daily_message_limit' => $setting->daily_message_limit,
                // Nilai rahasianya tidak pernah kembali; cukup statusnya.
                'api_key_configured' => filled($setting->gateway_api_key),
                'gateway_base_url' => $gateway?->base_url,
            ],
            'meta' => ['message' => __('broadcast.settings_saved')],
        ]);
    }

    /**
     * Alamat gateway satu untuk semua klinik, jadi perubahannya dicatat
     * lintas tenant di audit log.
     */
    private function saveGatewayBaseUrl(?string $baseUrl, Tenant $tenant): void
    {
        $gateway = WhatsappGatewaySetting::query()->firstOrNew([]);
        $previous = $gateway->base_url;

        if ($previous === $baseUrl) {
            return;
        }

        $gateway->base_url = $baseUrl;
        $gateway->save();

        app(LogAuditAction::class)->handle(
            'whatsapp.gateway_base_url_updated',
            $gateway,
            null,
            ['base_url' => ['old' => $previous, 'new' => $baseUrl]],
            'Memperbarui alamat gateway WhatsApp dari pengaturan klinik '.$tenant->name.'.',
            $tenant,
        );
    }
}
