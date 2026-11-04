<?php

namespace Tests\Feature\Whatsapp;

use App\Enums\ClinicRole;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Sesi gateway milik klinik: keadaan, penyiapan QR, kode pairing, dan aksi
 * putus/sambung ulang. Semua panggilan HTTP dibalas palsu; yang diuji adalah
 * kontrak antara aplikasi dan gateway, bukan gateway sungguhan.
 */
class GatewaySessionTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const SESSION_ID = '11111111-1111-1111-1111-111111111111';

    private const NEW_SESSION_ID = '9f1c2a54-1111-4222-8333-444455556666';

    private const BASE = 'https://gateway.test/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        config(['whatsapp_gateway.base_url' => 'https://gateway.test']);
    }

    /**
     * Pengaturan klinik yang sudah punya akun gateway. Tanpa
     * `gateway_session_id`, provisioning tetap no-op karena kredensialnya
     * lengkap.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function setting(array $overrides = []): WhatsappSetting
    {
        return WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            'gateway_tenant_key' => 'tenant-uji',
            'gateway_api_key' => 'api-uji',
            'gateway_webhook_secret' => 'secret-uji',
            'gateway_session_id' => self::SESSION_ID,
            ...$overrides,
        ]);
    }

    /** Baris pengaturan yang tersimpan, dibaca ulang dari basis data. */
    private function storedSetting(): WhatsappSetting
    {
        return WhatsappSetting::query()->firstOrFail();
    }

    public function test_state_reports_connected_session(): void
    {
        $this->setting();

        Http::fake(['*' => Http::response(['success' => true, 'data' => [
            'sessionId' => 'klinik-uji',
            'status' => 'CONNECTED',
            'phoneNumber' => '628111',
            'lastSeenAt' => null,
        ]])]);

        $this->actingAsClinicUser();
        $this->getJson($this->tenantUrl('broadcasts/connection'))
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.number', '628111')
            ->assertJsonPath('data.error', false);
    }

    public function test_state_reports_unprovisioned_clinic_without_calling_the_gateway(): void
    {
        Http::fake();

        $this->actingAsClinicUser();
        $this->getJson($this->tenantUrl('broadcasts/connection'))
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.provisioned', false)
            ->assertJsonPath('data.connected', false);

        Http::assertNothingSent();
    }

    /**
     * Klinik yang mengisi API key manual tidak punya tenant key maupun
     * webhook, tetapi sesinya tetap bisa dibaca dan dinyalakan.
     */
    public function test_state_is_available_with_only_a_manual_api_key(): void
    {
        $this->setting([
            'gateway_tenant_key' => null,
            'gateway_webhook_secret' => null,
        ]);

        Http::fake(['*' => Http::response(['success' => true, 'data' => [
            'sessionId' => 'klinik-uji',
            'status' => 'CONNECTED',
            'phoneNumber' => '628111',
            'lastSeenAt' => null,
        ]])]);

        $this->actingAsClinicUser();
        $this->getJson($this->tenantUrl('broadcasts/connection'))
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.number', '628111')
            ->assertJsonPath('data.provisioned', false);
    }

    public function test_prepare_creates_a_session_without_registering_a_gateway_account(): void
    {
        $this->setting([
            'gateway_tenant_key' => null,
            'gateway_webhook_secret' => null,
            'gateway_session_id' => null,
        ]);

        Http::fake(function ($request) {
            $url = $request->url();

            if ($url === self::BASE.'/sessions' && $request->method() === 'GET') {
                return Http::response(['success' => true, 'data' => [
                    'items' => [],
                    'meta' => ['page' => 1, 'limit' => 20, 'total' => 0, 'totalPages' => 0],
                ]]);
            }

            if ($url === self::BASE.'/sessions' && $request->method() === 'POST') {
                return Http::response(['success' => true, 'data' => [
                    'id' => self::NEW_SESSION_ID,
                    'sessionId' => 'klinik-uji',
                    'status' => 'INITIALIZING',
                ]], 201);
            }

            if (str_ends_with($url, '/status')) {
                return Http::response(['success' => true, 'data' => [
                    'sessionId' => 'klinik-uji',
                    'status' => 'DISCONNECTED',
                    'phoneNumber' => null,
                    'lastSeenAt' => null,
                ]]);
            }

            return Http::response(['success' => true, 'data' => ['status' => 'INITIALIZING']]);
        });

        $this->actingAsClinicUser();
        $this->postJson($this->tenantUrl('broadcasts/connection/prepare'))->assertOk();

        $this->assertSame(self::NEW_SESSION_ID, $this->storedSetting()->gateway_session_id);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/auth/'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api-keys'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/webhooks'));
    }

    public function test_prepare_creates_missing_session_then_returns_qr(): void
    {
        $this->setting(['gateway_session_id' => null]);

        Http::fake(function ($request) {
            $url = $request->url();

            if ($url === self::BASE.'/sessions' && $request->method() === 'GET') {
                return Http::response(['success' => true, 'data' => [
                    'items' => [],
                    'meta' => ['page' => 1, 'limit' => 20, 'total' => 0, 'totalPages' => 0],
                ]]);
            }

            if ($url === self::BASE.'/sessions' && $request->method() === 'POST') {
                return Http::response(['success' => true, 'data' => [
                    'id' => self::NEW_SESSION_ID,
                    'sessionId' => 'klinik-uji',
                    'name' => 'Klinik klinik-uji',
                    'status' => 'INITIALIZING',
                ]], 201);
            }

            if (str_ends_with($url, '/connect')) {
                return Http::response(['success' => true, 'data' => [
                    'sessionId' => 'klinik-uji',
                    'status' => 'INITIALIZING',
                    'phoneNumber' => null,
                    'lastSeenAt' => null,
                    'jobId' => 'job-1',
                ]]);
            }

            if (str_ends_with($url, '/status')) {
                return Http::response(['success' => true, 'data' => [
                    'sessionId' => 'klinik-uji',
                    'status' => 'QR_REQUIRED',
                    'phoneNumber' => null,
                    'lastSeenAt' => null,
                ]]);
            }

            if (str_ends_with($url, '/qr')) {
                return Http::response(['success' => true, 'data' => [
                    'sessionId' => 'klinik-uji',
                    'status' => 'QR_REQUIRED',
                    'qr' => 'data:image/png;base64,AAAA',
                    'expiresAt' => null,
                ]]);
            }

            return Http::response(['success' => false, 'message' => 'tak terduga'], 500);
        });

        $this->actingAsClinicUser();
        $response = $this->postJson($this->tenantUrl('broadcasts/connection/prepare'))
            ->assertOk()
            ->assertJsonPath('data.connected', false);

        $this->assertStringStartsWith('data:image/png;base64,', $response->json('data.qr'));

        // Id UUID dari gateway yang disimpan, bukan slug: seluruh endpoint
        // sesi memakai id itu.
        $this->assertSame(self::NEW_SESSION_ID, $this->storedSetting()->gateway_session_id);

        Http::assertSent(fn ($request) => $request->url() === self::BASE.'/sessions'
            && $request->method() === 'POST'
            && $request['sessionId'] === 'klinik-uji');
    }

    public function test_prepare_adopts_existing_session_with_same_slug(): void
    {
        $this->setting(['gateway_session_id' => null]);

        Http::fake(function ($request) {
            $url = $request->url();

            if ($url === self::BASE.'/sessions' && $request->method() === 'GET') {
                return Http::response(['success' => true, 'data' => [
                    'items' => [['id' => self::NEW_SESSION_ID, 'sessionId' => 'klinik-uji', 'status' => 'DISCONNECTED']],
                    'meta' => ['page' => 1, 'limit' => 20, 'total' => 1, 'totalPages' => 1],
                ]]);
            }

            if (str_ends_with($url, '/connect')) {
                return Http::response(['success' => true, 'data' => ['status' => 'INITIALIZING']]);
            }

            if (str_ends_with($url, '/status')) {
                return Http::response(['success' => true, 'data' => [
                    'sessionId' => 'klinik-uji',
                    'status' => 'QR_REQUIRED',
                    'phoneNumber' => null,
                    'lastSeenAt' => null,
                ]]);
            }

            if (str_ends_with($url, '/qr')) {
                return Http::response(['success' => true, 'data' => [
                    'sessionId' => 'klinik-uji',
                    'status' => 'QR_REQUIRED',
                    'qr' => 'data:image/png;base64,ADOPTED',
                    'expiresAt' => null,
                ]]);
            }

            return Http::response(['success' => false, 'message' => 'tak terduga'], 500);
        });

        $this->actingAsClinicUser();
        $response = $this->postJson($this->tenantUrl('broadcasts/connection/prepare'))
            ->assertOk()
            ->assertJsonPath('data.connected', false);

        $this->assertStringStartsWith('data:image/png;base64,', $response->json('data.qr'));

        $this->assertSame(self::NEW_SESSION_ID, $this->storedSetting()->gateway_session_id);

        // Sesi yang sudah minta QR tidak dinyalakan ulang: connect memulai
        // proses dari awal dan QR yang sudah terbit hilang dari layar.
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/connect'));

        Http::assertNotSent(fn ($request) => $request->url() === self::BASE.'/sessions'
            && $request->method() === 'POST');
    }

    /**
     * Menyalakan ulang sesi yang masih berjalan hanya menunda QR-nya.
     */
    public function test_prepare_does_not_reconnect_a_session_that_is_already_working(): void
    {
        $this->setting();
        $this->actingAsClinicUser();

        $current = 'INITIALIZING';

        Http::fake(function ($request) use (&$current) {
            if (str_ends_with($request->url(), '/status')) {
                return Http::response(['success' => true, 'data' => [
                    'sessionId' => 'klinik-uji',
                    'status' => $current,
                    'phoneNumber' => null,
                    'lastSeenAt' => null,
                ]]);
            }

            if (str_ends_with($request->url(), '/qr')) {
                return Http::response(['success' => true, 'data' => [
                    'sessionId' => 'klinik-uji',
                    'status' => 'QR_REQUIRED',
                    'qr' => 'data:image/png;base64,AAAA',
                    'expiresAt' => null,
                ]]);
            }

            return Http::response(['success' => true, 'data' => []]);
        });

        foreach (['INITIALIZING', 'QR_REQUIRED'] as $status) {
            // Jendela cooldown prepare berlaku per klinik; dibersihkan agar
            // kedua keadaan diuji sungguhan, bukan yang pertama saja.
            Cache::flush();
            $current = $status;

            $this->postJson($this->tenantUrl('broadcasts/connection/prepare'))->assertOk();
        }

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/connect'));
    }

    /**
     * Dialog Koneksi tidak boleh menyuruh admin membuka menu yang sama untuk
     * membuat baris pengaturannya. Barisnya dibuat saat prepare, lalu
     * provisioning tetap berjalan.
     */
    public function test_prepare_creates_the_setting_row_instead_of_returning_early(): void
    {
        Http::fake(['*' => Http::response([
            'success' => false,
            'message' => 'Gateway belum siap.',
            'error' => ['code' => 'UNAVAILABLE'],
        ], 503)]);

        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('broadcasts/connection/prepare'))->assertOk();

        $this->assertDatabaseHas('whatsapp_settings', [
            'tenant_id' => $this->tenant->id,
            'session' => $this->tenant->slug,
        ]);

        // Provisioning benar-benar dicoba, bukan keluar lebih awal.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/auth/'));
    }

    /**
     * Status lain (mis. DISCONNECTED) memang perlu dinyalakan.
     */
    public function test_prepare_connects_a_disconnected_session(): void
    {
        $this->setting();

        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/status')) {
                return Http::response(['success' => true, 'data' => [
                    'sessionId' => 'klinik-uji',
                    'status' => 'DISCONNECTED',
                    'phoneNumber' => null,
                    'lastSeenAt' => null,
                ]]);
            }

            return Http::response(['success' => true, 'data' => ['status' => 'INITIALIZING']]);
        });

        $this->actingAsClinicUser();
        $this->postJson($this->tenantUrl('broadcasts/connection/prepare'))->assertOk();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/connect')
            && $request->method() === 'POST');
    }

    public function test_prepare_twice_within_the_cooldown_connects_once(): void
    {
        $this->setting();

        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/status')) {
                return Http::response(['success' => true, 'data' => [
                    'sessionId' => 'klinik-uji',
                    'status' => 'DISCONNECTED',
                    'phoneNumber' => null,
                    'lastSeenAt' => null,
                ]]);
            }

            return Http::response(['success' => true, 'data' => ['status' => 'INITIALIZING']]);
        });

        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('broadcasts/connection/prepare'))->assertOk();
        $this->postJson($this->tenantUrl('broadcasts/connection/prepare'))->assertOk();

        $connects = Http::recorded(fn ($request) => str_ends_with($request->url(), '/connect'))->count();

        $this->assertSame(1, $connects, 'double-submit menyalakan sesi lebih dari sekali');
    }

    public function test_polling_state_never_touches_the_session(): void
    {
        $this->setting();

        Http::fake(['*' => Http::response(['success' => true, 'data' => [
            'sessionId' => 'klinik-uji',
            'status' => 'ERROR',
            'phoneNumber' => null,
            'lastSeenAt' => null,
        ]])]);

        $this->actingAsClinicUser();

        foreach (range(1, 5) as $ignored) {
            $this->getJson($this->tenantUrl('broadcasts/connection'))->assertOk();
        }

        $mutations = Http::recorded(fn ($request) => $request->method() !== 'GET')->count();

        $this->assertSame(0, $mutations, 'polling ikut menyalakan atau memulai ulang sesi');
    }

    public function test_state_reports_error_instead_of_throwing_when_gateway_is_down(): void
    {
        $this->setting();

        Http::fake(['*' => Http::response(['message' => 'gateway mati'], 503)]);

        $this->actingAsClinicUser();
        $this->getJson($this->tenantUrl('broadcasts/connection'))
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.error', true);
    }

    public function test_pairing_code_is_returned_to_the_admin(): void
    {
        $this->setting();

        Http::fake([self::BASE.'/sessions/'.self::SESSION_ID.'/pairing-code' => Http::response([
            'success' => true,
            'data' => [
                'sessionId' => 'klinik-uji',
                'status' => 'QR_REQUIRED',
                'pairingCode' => 'ABCD1234',
                'expiresAt' => '2026-11-04T10:00:00.000Z',
            ],
        ], 202)]);

        $this->actingAsClinicUser();
        $this->postJson($this->tenantUrl('broadcasts/connection/pairing'), ['phone' => '0812-0000-0000'])
            ->assertStatus(202)
            ->assertJsonPath('data.pairing_code', 'ABCD1234');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/pairing-code')
            && $request['phoneNumber'] === '6281200000000');
    }

    public function test_invalid_pairing_phone_is_rejected_before_calling_the_gateway(): void
    {
        $this->setting();
        Http::fake();

        $this->actingAsClinicUser();
        $this->postJson($this->tenantUrl('broadcasts/connection/pairing'), ['phone' => '123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        Http::assertNothingSent();
    }

    public function test_slug_cannot_be_edited_once_gateway_session_exists(): void
    {
        $this->setting();

        $this->actingAsClinicUser();
        $this->putJson($this->tenantUrl('broadcasts/settings'), ['session' => 'nama-lain'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('session');

        // Nilai yang sama tetap boleh disimpan; formulir mengirim ulang semua
        // isian, bukan hanya yang berubah.
        $this->putJson($this->tenantUrl('broadcasts/settings'), ['session' => 'klinik-uji'])
            ->assertOk();
    }

    /**
     * Peran yang hanya boleh melihat keadaan tidak boleh menyalakan sesi.
     * Membaca layar Koneksi cukup `viewAny`; menekan "Sambungkan ulang"
     * mengubah keadaan gateway dan butuh `update`.
     */
    public function test_view_only_role_can_read_but_not_prepare_the_session(): void
    {
        $this->setting();
        Http::fake();

        $user = $this->actingAsClinicUser(ClinicRole::Cashier);
        $user->givePermissionTo('broadcast.view');

        $this->getJson($this->tenantUrl('broadcasts/connection'))->assertOk();
        $this->postJson($this->tenantUrl('broadcasts/connection/prepare'))->assertForbidden();
    }

    public function test_view_only_role_cannot_delete_the_session(): void
    {
        $this->setting();
        Http::fake();

        $user = $this->actingAsClinicUser(ClinicRole::Cashier);
        $user->givePermissionTo('broadcast.view');

        $this->deleteJson($this->tenantUrl('broadcasts/connection'))->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_delete_connection_calls_gateway_and_clears_the_session_id(): void
    {
        $this->setting();
        Http::fake([self::BASE.'/sessions/'.self::SESSION_ID => Http::response('', 204)]);

        $this->actingAsClinicUser();

        $this->deleteJson($this->tenantUrl('broadcasts/connection'))
            ->assertOk()
            ->assertJsonPath('meta.message', __('broadcast.session_deleted'));

        Http::assertSent(fn ($request) => $request->url() === self::BASE.'/sessions/'.self::SESSION_ID
            && $request->method() === 'DELETE');

        $stored = $this->storedSetting();
        $this->assertNull($stored->gateway_session_id);
        // Nama sesi tetap disimpan supaya tinggal disambungkan ulang.
        $this->assertSame('klinik-uji', $stored->session);
    }

    /** Sesi yang sudah tidak ada di gateway sama saja dengan sudah dihapus. */
    public function test_delete_connection_treats_a_missing_gateway_session_as_success(): void
    {
        $this->setting();
        Http::fake(['*' => Http::response([
            'success' => false,
            'message' => 'Sesi tidak ditemukan.',
            'error' => ['code' => 'SESSION_NOT_FOUND'],
        ], 404)]);

        $this->actingAsClinicUser();

        $this->deleteJson($this->tenantUrl('broadcasts/connection'))->assertOk();

        $this->assertNull($this->storedSetting()->gateway_session_id);
    }

    public function test_delete_connection_without_a_session_is_still_ok(): void
    {
        $this->setting(['gateway_session_id' => null]);
        Http::fake();

        $this->actingAsClinicUser();

        $this->deleteJson($this->tenantUrl('broadcasts/connection'))
            ->assertOk()
            ->assertJsonPath('meta.message', __('broadcast.session_none'));

        Http::assertNothingSent();
    }

    public function test_connection_actions_reach_their_gateway_endpoints(): void
    {
        $this->setting();

        Http::fake([self::BASE.'/sessions/*' => Http::response(['success' => true, 'data' => []])]);

        $this->actingAsClinicUser();

        foreach (['restart', 'disconnect', 'logout'] as $action) {
            $this->postJson($this->tenantUrl('broadcasts/connection/'.$action))->assertOk();
        }

        foreach (['restart', 'disconnect', 'logout'] as $action) {
            Http::assertSent(fn ($request) => $request->url() === self::BASE.'/sessions/'.self::SESSION_ID.'/'.$action
                && $request->method() === 'POST');
        }
    }
}
