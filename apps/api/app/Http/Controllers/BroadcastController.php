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
use App\Http\Requests\BroadcastUpdateRequest;
use App\Http\Requests\WhatsappPairingRequest;
use App\Http\Requests\WhatsappSessionRequest;
use App\Http\Requests\WhatsappTemplateRequest;
use App\Http\Resources\BroadcastRecipientResource;
use App\Http\Resources\BroadcastResource;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use App\Services\BroadcastService;
use App\Services\WhatsappGatewaySessionService;
use App\Services\WhatsappTemplateService;
use App\Support\BroadcastAudienceBuilder;
use App\Support\CloudApiClient;
use App\Support\DailyMessageQuota;
use App\Support\PhoneNumber;
use App\Support\TenantCache;
use App\Support\WhatsappGatewayException;
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

    public function show(Broadcast $broadcast): JsonResponse
    {
        $this->authorize('view', $broadcast);

        $broadcast->load(['creator', 'recipients' => fn ($q) => $q->orderBy('name')])
            ->loadCount(['recipients', 'sentRecipients', 'pendingRecipients', 'failedRecipients']);

        $client = app(CloudApiClient::class);

        return response()->json([
            'data' => new BroadcastResource($broadcast),
            // Template bisa dikelola tanpa nomor pengirim, tapi kirim tidak.
            'meta' => ['cloud_api_ready' => $client instanceof CloudApiClient && $client->canSend()],
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
     * Template resmi yang sudah disetujui Meta untuk klinik ini.
     *
     * Hanya template APPROVED yang bisa dipakai mengirim; daftar ini yang
     * mengisi pemilih template di layar broadcast.
     */
    public function templates(): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        $client = app(CloudApiClient::class);

        if (! $client instanceof CloudApiClient) {
            return response()->json(['data' => [], 'meta' => ['ready' => false]]);
        }

        try {
            $templates = $client->approvedTemplates();
        } catch (Throwable $e) {
            Log::error('Gagal mengambil daftar template WhatsApp Cloud API.', [
                'exception' => $e,
            ]);

            return response()->json(['data' => [], 'meta' => ['ready' => false]]);
        }

        return response()->json(['data' => $templates, 'meta' => ['ready' => true]]);
    }

    /**
     * Semua template klinik beserta status peninjauannya — layar kelola
     * template, bukan pemilih template.
     */
    public function manageTemplates(WhatsappTemplateService $templates): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        $result = $templates->all();

        return response()->json([
            'data' => $result['templates'],
            'meta' => ['ready' => $result['ready']],
        ]);
    }

    /** Daftarkan template baru; Meta meninjaunya sebelum bisa dipakai. */
    public function storeTemplate(WhatsappTemplateRequest $request, WhatsappTemplateService $templates): JsonResponse
    {
        $this->authorize('update', Broadcast::class);

        $template = $templates->create($request->validated());

        return response()->json([
            'data' => $template,
            'meta' => ['message' => __('broadcast.template_created')],
        ], 201);
    }

    /** Hapus template; `hsm_id` memilih versi bahasa tertentu bila ada. */
    public function destroyTemplate(
        Request $request,
        string $name,
        WhatsappTemplateService $templates,
    ): JsonResponse {
        $this->authorize('update', Broadcast::class);

        $hsmId = $request->query('hsm_id');
        $hsmId = is_string($hsmId) && $hsmId !== '' ? $hsmId : null;

        $templates->delete($name, $hsmId);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('broadcast.template_deleted')],
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
        $this->authorize('viewAny', Broadcast::class);

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
            fn (): array => $sessions->pairing($request->validated()['phone']),
            202,
        );
    }

    public function restartConnection(WhatsappGatewaySessionService $sessions): JsonResponse
    {
        $this->authorize('update', Broadcast::class);

        return $this->connectionAction(fn () => $sessions->restart());
    }

    public function disconnectConnection(WhatsappGatewaySessionService $sessions): JsonResponse
    {
        $this->authorize('update', Broadcast::class);

        return $this->connectionAction(fn () => $sessions->disconnect());
    }

    public function logoutConnection(WhatsappGatewaySessionService $sessions): JsonResponse
    {
        $this->authorize('update', Broadcast::class);

        return $this->connectionAction(fn () => $sessions->logout());
    }

    /**
     * Jalankan aksi sesi dan teruskan penolakan gateway apa adanya: kode
     * statusnya membedakan sesi yang sudah terhubung dari gateway yang mati.
     *
     * @param  callable(): mixed  $action
     */
    private function connectionAction(callable $action, int $status = 200): JsonResponse
    {
        try {
            $data = $action();
        } catch (Throwable $e) {
            Log::error('Aksi sesi gateway WhatsApp klinik gagal.', ['exception' => $e]);

            return response()->json(
                ['message' => $e->getMessage()],
                $e instanceof WhatsappGatewayException && $e->status > 0 ? $e->status : 502,
            );
        }

        return response()->json(['data' => $data, 'meta' => []], $status);
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

        return response()->json([
            'data' => [
                'session' => $setting?->session,
                // Nomor Cloud API Meta yang dipakai membalas lewat jalur itu.
                'phone_number_id' => $setting?->phone_number_id,
                // Akun WhatsApp Business tempat template resmi klinik
                // bernaung; kosong berarti pemilih template belum bisa diisi.
                'waba_id' => $setting?->waba_id,
                // Template yang dipakai pengingat otomatis.
                'reminder_template' => $setting?->reminder_template,
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

        // Hanya ditimpa bila dikirim: menyimpan satu bagian tidak boleh
        // menghapus bagian lain yang sudah diisi sebelumnya. Kolom Meta tidak
        // lagi diterima di sini; jalur pengirimannya sudah pindah ke gateway.
        foreach (['session', 'reminder_template'] as $field) {
            if (array_key_exists($field, $validated)) {
                $setting->{$field} = $validated[$field];
            }
        }

        $setting->save();

        return response()->json([
            'data' => [
                'session' => $setting->session,
                'phone_number_id' => $setting->phone_number_id,
                'waba_id' => $setting->waba_id,
                'reminder_template' => $setting->reminder_template,
            ],
            'meta' => ['message' => __('broadcast.settings_saved')],
        ]);
    }
}
