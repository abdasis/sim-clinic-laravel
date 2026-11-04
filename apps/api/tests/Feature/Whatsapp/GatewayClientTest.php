<?php

namespace Tests\Feature\Whatsapp;

use App\Enums\ConversationChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\WhatsappSetting;
use App\Support\WhatsappGatewayClient;
use App\Support\WhatsappGatewayException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Kontrak HTTP klien gateway WhatsApp internal: header kredensial klinik,
 * bentuk payload, pembacaan envelope, dan penerjemahan galat.
 */
class GatewayClientTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const SESSION_ID = '11111111-1111-1111-1111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        config(['whatsapp_gateway.base_url' => 'https://gateway.test']);
    }

    /**
     * Pengaturan klinik dengan kredensial gateway lengkap. Nilainya sengaja
     * pendek supaya mudah dicocokkan di assertion header.
     *
     * @param  array<string, string|null>  $overrides
     */
    private function gatewaySetting(array $overrides = []): WhatsappSetting
    {
        return WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            'gateway_tenant_key' => 'tenant-uji',
            'gateway_api_key' => 'api-uji',
            'gateway_session_id' => self::SESSION_ID,
            ...$overrides,
        ]);
    }

    public function test_send_text_posts_headers_payload_and_returns_message_id(): void
    {
        Http::fake(['gateway.test/api/v1/messages/send' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => ['messageId' => '4d5e6f70-1a2b-4c3d-8e9f-0a1b2c3d4e5f', 'status' => 'QUEUED'],
        ], 202)]);

        $id = app(WhatsappGatewayClient::class)
            ->sendText($this->gatewaySetting(), '081234567890', 'Halo', 'inbox-uji-123');

        $this->assertSame('4d5e6f70-1a2b-4c3d-8e9f-0a1b2c3d4e5f', $id);
        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/messages/send'
            && $request->hasHeader('x-tenant-key', 'tenant-uji')
            && $request->hasHeader('X-API-Key', 'api-uji')
            && $request->hasHeader('Idempotency-Key', 'inbox-uji-123')
            && $request['sessionId'] === self::SESSION_ID
            && $request['to'] === '081234567890'
            && $request['text'] === 'Halo');
    }

    /**
     * Kredensial yang diisi manual dari dashboard gateway hanya berupa API
     * key; tanpa tenant key header itu tidak boleh ikut terkirim.
     */
    public function test_tenant_key_header_is_omitted_when_only_an_api_key_is_set(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => ['messageId' => 'manual-uji', 'status' => 'QUEUED'],
        ], 202)]);

        app(WhatsappGatewayClient::class)->sendText(
            $this->gatewaySetting(['gateway_tenant_key' => null]),
            '0812',
            'Halo',
            'inbox-manual',
        );

        Http::assertSent(fn ($request) => $request->hasHeader('X-API-Key', 'api-uji')
            && ! $request->hasHeader('x-tenant-key'));
    }

    public function test_send_image_posts_url_and_caption(): void
    {
        Http::fake(['gateway.test/api/v1/messages/image' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => ['messageId' => 'gambar-uji', 'status' => 'QUEUED'],
        ], 202)]);

        $id = app(WhatsappGatewayClient::class)->sendImage(
            $this->gatewaySetting(),
            '081234567890',
            'https://contoh.test/poster.png',
            'Promo bulan ini',
            'broadcast-9-0',
        );

        $this->assertSame('gambar-uji', $id);
        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/messages/image'
            && $request['sessionId'] === self::SESSION_ID
            && $request['url'] === 'https://contoh.test/poster.png'
            && $request['caption'] === 'Promo bulan ini'
            && $request->hasHeader('Idempotency-Key', 'broadcast-9-0'));
    }

    public function test_error_envelope_becomes_gateway_exception(): void
    {
        Http::fake(['*' => Http::response([
            'success' => false,
            'message' => 'Sesi belum terhubung.',
            'error' => ['code' => 'SESSION_NOT_CONNECTED', 'details' => []],
        ], 422)]);

        try {
            app(WhatsappGatewayClient::class)
                ->sendText($this->gatewaySetting(), '0812', 'Halo', 'inbox-uji-123');
            $this->fail('Seharusnya melempar.');
        } catch (WhatsappGatewayException $e) {
            $this->assertSame('SESSION_NOT_CONNECTED', $e->code);
            $this->assertSame(422, $e->status);
            $this->assertSame('Sesi belum terhubung.', $e->getMessage());
        }
    }

    /**
     * Balasan 2xx yang bukan envelope bukan keberhasilan. Dulu pemanggilnya
     * menerima exception berstatus 200 — status yang justru menandakan
     * "berhasil" di seluruh lapisan di atasnya.
     */
    public function test_successful_response_without_an_envelope_is_mapped_to_bad_gateway(): void
    {
        Http::fake(['*' => Http::response('<html>proxy</html>', 200)]);

        try {
            app(WhatsappGatewayClient::class)
                ->sendText($this->gatewaySetting(), '0812', 'Halo', 'inbox-uji-123');
            $this->fail('Seharusnya melempar.');
        } catch (WhatsappGatewayException $e) {
            $this->assertSame('INVALID_RESPONSE', $e->code);
            $this->assertSame(502, $e->status);
        }
    }

    public function test_rate_limit_carries_retry_after(): void
    {
        Http::fake(['*' => Http::response([
            'success' => false,
            'message' => 'Kuota habis.',
            'error' => ['code' => 'RATE_LIMIT_EXCEEDED'],
        ], 429, ['Retry-After' => '17'])]);

        try {
            app(WhatsappGatewayClient::class)
                ->sendText($this->gatewaySetting(), '0812', 'Halo', 'inbox-uji-123');
            $this->fail('Seharusnya melempar.');
        } catch (WhatsappGatewayException $e) {
            $this->assertSame('RATE_LIMIT_EXCEEDED', $e->code);
            $this->assertSame(17, $e->retryAfter);
        }
    }

    /**
     * Panduan gateway tidak membatasi format `Retry-After`; HTTP mengizinkan
     * tanggal. Tanggal yang masih di depan diterjemahkan ke selisih detik.
     */
    public function test_rate_limit_accepts_retry_after_as_http_date(): void
    {
        Http::fake(['*' => Http::response([
            'success' => false,
            'message' => 'Kuota habis.',
            'error' => ['code' => 'RATE_LIMIT_EXCEEDED'],
        ], 429, ['Retry-After' => now()->addSeconds(60)->toRfc7231String()])]);

        try {
            app(WhatsappGatewayClient::class)
                ->sendText($this->gatewaySetting(), '0812', 'Halo', 'inbox-uji-123');
            $this->fail('Seharusnya melempar.');
        } catch (WhatsappGatewayException $e) {
            $this->assertEqualsWithDelta(60, $e->retryAfter, 2);
        }
    }

    /** Tanggal yang sudah lewat tidak boleh menghasilkan jeda negatif. */
    public function test_rate_limit_treats_past_http_date_as_zero(): void
    {
        Http::fake(['*' => Http::response([
            'success' => false,
            'message' => 'Kuota habis.',
            'error' => ['code' => 'RATE_LIMIT_EXCEEDED'],
        ], 429, ['Retry-After' => now()->subMinutes(5)->toRfc7231String()])]);

        try {
            app(WhatsappGatewayClient::class)
                ->sendText($this->gatewaySetting(), '0812', 'Halo', 'inbox-uji-123');
            $this->fail('Seharusnya melempar.');
        } catch (WhatsappGatewayException $e) {
            $this->assertSame(0, $e->retryAfter);
        }
    }

    /** Header yang tidak bisa dibaca bukan alasan menebak; biarkan null. */
    public function test_rate_limit_ignores_unparsable_retry_after(): void
    {
        Http::fake(['*' => Http::response([
            'success' => false,
            'message' => 'Kuota habis.',
            'error' => ['code' => 'RATE_LIMIT_EXCEEDED'],
        ], 429, ['Retry-After' => 'sebentar lagi'])]);

        try {
            app(WhatsappGatewayClient::class)
                ->sendText($this->gatewaySetting(), '0812', 'Halo', 'inbox-uji-123');
            $this->fail('Seharusnya melempar.');
        } catch (WhatsappGatewayException $e) {
            $this->assertNull($e->retryAfter);
        }
    }

    public function test_network_failure_becomes_gateway_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('Koneksi ditolak.'));

        try {
            app(WhatsappGatewayClient::class)
                ->sendText($this->gatewaySetting(), '0812', 'Halo', 'inbox-uji-123');
            $this->fail('Seharusnya melempar.');
        } catch (WhatsappGatewayException $e) {
            $this->assertSame('NETWORK_ERROR', $e->code);
            $this->assertSame(0, $e->status);
        }
    }

    /**
     * Base URL dari pengaturan central boleh ditulis dengan atau tanpa
     * `/api/v1`; klien selalu menyusunnya sendiri sehingga tidak pernah
     * berakhir dengan `/api/v1/api/v1`.
     */
    public function test_base_url_is_normalized_before_appending_api_version(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        $setting = $this->gatewaySetting();

        foreach (['https://gateway.test/', 'https://gateway.test/api/v1', 'https://gateway.test/api/v1/'] as $baseUrl) {
            (new WhatsappGatewayClient($baseUrl))->sessionStatus($setting);
        }

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/sessions/'.self::SESSION_ID.'/status');
        $this->assertCount(3, Http::recorded());
    }

    public function test_register_and_login_normalize_auth_envelope(): void
    {
        Http::fake([
            'gateway.test/api/v1/auth/register' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'tenant' => ['id' => 't1', 'name' => 'Klinik', 'key' => 'tenant-register'],
                    'token' => ['accessToken' => 'access-register', 'refreshToken' => 'refresh-register'],
                    'user' => [],
                ],
            ], 201),
            'gateway.test/api/v1/auth/login' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'tenantKey' => 'tenant-login',
                    'token' => ['accessToken' => 'access-login', 'refreshToken' => 'refresh-login'],
                    'user' => [],
                ],
            ], 200),
        ]);

        $client = app(WhatsappGatewayClient::class);

        $this->assertSame([
            'tenant_key' => 'tenant-register',
            'access_token' => 'access-register',
            'refresh_token' => 'refresh-register',
        ], $client->register('Klinik Uji', 'Klinik Uji', 'tenant-1@gateway.local', 'rahasia-panjang'));

        $this->assertSame([
            'tenant_key' => 'tenant-login',
            'access_token' => 'access-login',
            'refresh_token' => 'refresh-login',
        ], $client->login('tenant-1@gateway.local', 'rahasia-panjang'));

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/auth/register'
            && ! $request->hasHeader('x-tenant-key')
            && ! $request->hasHeader('X-API-Key'));
    }

    public function test_create_api_key_uses_bearer_token_and_returns_key(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => ['id' => 'k1', 'key' => 'api-baru', 'name' => 'sim-clinic'],
        ], 201)]);

        $key = app(WhatsappGatewayClient::class)
            ->createApiKey('tenant-uji', 'access-uji', 'sim-clinic');

        $this->assertSame('api-baru', $key);
        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/api-keys'
            && $request->hasHeader('Authorization', 'Bearer access-uji')
            && $request->hasHeader('x-tenant-key', 'tenant-uji')
            && $request['name'] === 'sim-clinic');
    }

    public function test_session_reads_and_lifecycle_use_expected_paths(): void
    {
        $uuid = self::SESSION_ID;

        // Daftar dan pembuatan sesi memakai URL yang sama, jadi pemilihan
        // balasan harus melihat method-nya juga.
        Http::fake(fn ($request) => match (true) {
            $request->url() === 'https://gateway.test/api/v1/sessions' && $request->method() === 'GET' => Http::response(['success' => true, 'data' => [
                ['id' => 's1', 'sessionId' => 'klinik-uji', 'status' => 'CONNECTED'],
            ]], 200),
            $request->url() === 'https://gateway.test/api/v1/sessions' => Http::response(['success' => true, 'data' => [
                'id' => 's1', 'sessionId' => 'klinik-uji', 'status' => 'DISCONNECTED',
            ]], 201),
            $request->url() === 'https://gateway.test/api/v1/sessions/'.$uuid.'/status' => Http::response(['success' => true, 'data' => [
                'sessionId' => 'klinik-uji', 'status' => 'CONNECTED', 'phoneNumber' => '628111', 'lastSeenAt' => null,
            ]], 200),
            $request->url() === 'https://gateway.test/api/v1/sessions/'.$uuid.'/qr' => Http::response(['success' => true, 'data' => [
                'sessionId' => 'klinik-uji', 'status' => 'QR_REQUIRED', 'qr' => 'data:image/png;base64,AAA', 'expiresAt' => null,
            ]], 200),
            $request->url() === 'https://gateway.test/api/v1/sessions/'.$uuid.'/pairing-code' => Http::response(['success' => true, 'data' => [
                'sessionId' => 'klinik-uji', 'status' => 'QR_REQUIRED', 'pairingCode' => 'ABCD1234', 'expiresAt' => '2026-11-04T10:05:00Z',
            ]], 202),
            $request->url() === 'https://gateway.test/api/v1/sessions/'.$uuid.'/connect' => Http::response(['success' => true, 'data' => [
                'sessionId' => 'klinik-uji', 'status' => 'CONNECTED', 'phoneNumber' => null, 'lastSeenAt' => null, 'jobId' => 'j1',
            ]], 202),
            default => Http::response('', 204),
        });

        $client = app(WhatsappGatewayClient::class);
        $setting = $this->gatewaySetting();

        $this->assertSame('CONNECTED', $client->sessionStatus($setting)['status']);
        $this->assertSame('klinik-uji', $client->listSessions($setting)[0]['sessionId']);
        $this->assertSame('ABCD1234', $client->pairingCode($setting, '081234567890')['pairingCode']);
        $this->assertSame('data:image/png;base64,AAA', $client->qrCode($setting)['qr']);
        $this->assertSame('klinik-uji', $client->createSession($setting, 'Klinik Uji', 'klinik-uji')['sessionId']);
        $this->assertSame('j1', $client->connectSession($setting)['jobId']);

        $client->restartSession($setting);
        $client->disconnectSession($setting);
        $client->logoutSession($setting);
        $client->deleteSession($setting);

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/sessions'
            && $request->method() === 'POST'
            && $request['sessionId'] === 'klinik-uji'
            && $request['name'] === 'Klinik Uji');
        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/sessions/'.$uuid
            && $request->method() === 'DELETE');
        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/sessions/'.$uuid.'/pairing-code'
            && $request['phoneNumber'] === '081234567890');
    }

    /**
     * Pagar jatah harian berdiri sebelum request berangkat. Nomor yang sudah
     * menyentuh batas justru paling rawan diblokir; mengirim satu pesan lagi
     * tidak sepadan.
     */
    public function test_send_is_refused_without_http_once_the_daily_quota_is_spent(): void
    {
        Http::fake();
        config(['broadcast.daily_limit' => 1]);

        $this->outboundToday(1);

        try {
            app(WhatsappGatewayClient::class)
                ->sendText($this->gatewaySetting(), '0812', 'Halo', 'inbox-uji-123');
            $this->fail('Seharusnya melempar.');
        } catch (WhatsappGatewayException $e) {
            $this->assertSame('DAILY_LIMIT_REACHED', $e->code);
            $this->assertSame(429, $e->status);
            $this->assertNotNull($e->retryAfter);
            $this->assertSame(__('broadcast.daily_limit_reached', ['limit' => 1]), $e->getMessage());
        }

        Http::assertNothingSent();
    }

    /** Selama jatahnya masih ada, pesannya tetap berangkat. */
    public function test_send_still_goes_out_below_the_daily_quota(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => ['messageId' => 'masih-boleh', 'status' => 'QUEUED'],
        ], 202)]);
        config(['broadcast.daily_limit' => 2]);

        $this->outboundToday(1);

        $id = app(WhatsappGatewayClient::class)
            ->sendText($this->gatewaySetting(), '0812', 'Halo', 'inbox-uji-123');

        $this->assertSame('masih-boleh', $id);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/messages/send'));
    }

    /** Satu pesan keluar hari ini, dari jalur mana pun. */
    private function outboundToday(int $count): void
    {
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'contact_phone' => '6281234567890',
            'channel' => ConversationChannel::Gateway,
            'last_message_at' => now(),
        ]);

        foreach (range(1, $count) as $i) {
            $conversation->messages()->create([
                'direction' => MessageDirection::Out,
                'body' => 'Halo '.$i,
                'status' => MessageStatus::Sent,
                'sent_at' => now()->subHour(),
            ]);
        }
    }

    public function test_missing_clinic_credentials_throws_indonesian_exception(): void
    {
        $setting = $this->gatewaySetting(['gateway_api_key' => null]);

        $this->expectException(WhatsappGatewayException::class);
        $this->expectExceptionMessage('Kredensial gateway klinik belum lengkap. Hubungkan WhatsApp terlebih dahulu.');

        app(WhatsappGatewayClient::class)->sendText($setting, '0812', 'Halo', 'inbox-uji-123');
    }

    public function test_missing_gateway_session_throws_clear_exception(): void
    {
        $setting = $this->gatewaySetting(['gateway_session_id' => null]);

        $this->expectException(WhatsappGatewayException::class);
        $this->expectExceptionMessage('Sesi WhatsApp klinik belum tersedia di gateway.');

        app(WhatsappGatewayClient::class)->sessionStatus($setting);
    }

    public function test_create_webhook_returns_id_and_secret(): void
    {
        Http::fake(['gateway.test/api/v1/webhooks' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => ['id' => 'w1', 'secret' => 'secret-uji'],
        ], 201)]);

        $webhook = app(WhatsappGatewayClient::class)->createWebhook(
            'tenant-uji',
            'api-uji',
            'https://app.test/api/whatsapp/webhook/token-uji',
            ['message.received', 'message.failed'],
        );

        $this->assertSame(['id' => 'w1', 'secret' => 'secret-uji'], $webhook);
        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/webhooks'
            && $request->hasHeader('x-tenant-key', 'tenant-uji')
            && $request->hasHeader('X-API-Key', 'api-uji')
            && $request['name'] === config('app.name')
            && $request['url'] === 'https://app.test/api/whatsapp/webhook/token-uji'
            && $request['events'] === ['message.received', 'message.failed']);
    }

    /**
     * Mode manual tidak punya tenant key; header itu tidak boleh ikut
     * terkirim kosong karena gateway bisa menganggapnya kredensial lain.
     */
    public function test_create_webhook_without_tenant_key_sends_api_key_only(): void
    {
        Http::preventStrayRequests();
        Http::fake(['gateway.test/api/v1/webhooks' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => ['id' => 'w1', 'secret' => 'secret-uji'],
        ], 201)]);

        app(WhatsappGatewayClient::class)->createWebhook(
            null,
            'api-uji',
            'https://app.test/api/whatsapp/webhook/token-uji',
            ['message.received'],
        );

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/webhooks'
            && $request->hasHeader('X-API-Key', 'api-uji')
            && ! $request->hasHeader('x-tenant-key'));
    }

    public function test_create_webhook_with_tenant_key_still_sends_it(): void
    {
        Http::preventStrayRequests();
        Http::fake(['gateway.test/api/v1/webhooks' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => ['id' => 'w1', 'secret' => 'secret-uji'],
        ], 201)]);

        app(WhatsappGatewayClient::class)->createWebhook(
            'tenant-uji',
            'api-uji',
            'https://app.test/api/whatsapp/webhook/token-uji',
            ['message.received'],
        );

        Http::assertSent(fn ($request) => $request->hasHeader('X-API-Key', 'api-uji')
            && $request->hasHeader('x-tenant-key', 'tenant-uji'));
    }

    public function test_presence_posts_composing_with_target_phone(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['queued' => true]], 202)]);

        $client = app(WhatsappGatewayClient::class);
        $setting = $this->gatewaySetting();

        $client->presence($setting, 'composing', '628123');
        // Tanpa nomor berarti broadcast; `to` tidak boleh ikut terkirim.
        $client->presence($setting, 'paused');

        $url = 'https://gateway.test/api/v1/sessions/'.self::SESSION_ID.'/presence';

        Http::assertSent(fn ($request) => $request->url() === $url
            && $request['presence'] === 'composing'
            && $request['to'] === '628123'
            && $request->hasHeader('x-tenant-key', 'tenant-uji'));
        Http::assertSent(fn ($request) => $request->url() === $url
            && $request['presence'] === 'paused'
            && ! array_key_exists('to', $request->data()));
    }

    public function test_presence_failure_is_logged_and_swallowed(): void
    {
        Http::fake(['*' => Http::response([
            'success' => false,
            'message' => 'Sesi belum terhubung.',
            'error' => ['code' => 'SESSION_NOT_CONNECTED'],
        ], 422)]);
        Log::spy();

        app(WhatsappGatewayClient::class)->presence($this->gatewaySetting(), 'composing', '628123');

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_document_and_audio_use_their_endpoints(): void
    {
        Http::fake([
            'gateway.test/api/v1/messages/document' => Http::response([
                'success' => true,
                'data' => ['messageId' => 'dok-uji', 'status' => 'QUEUED'],
            ], 202),
            'gateway.test/api/v1/messages/audio' => Http::response([
                'success' => true,
                'data' => ['messageId' => 'audio-uji', 'status' => 'QUEUED'],
            ], 202),
        ]);

        $client = app(WhatsappGatewayClient::class);
        $setting = $this->gatewaySetting();

        $this->assertSame('dok-uji', $client->sendDocument(
            $setting,
            '081234567890',
            'https://contoh.test/berkas.pdf',
            'berkas.pdf',
            'Lampiran hasil',
            'inbox-dok',
        ));
        $this->assertSame('audio-uji', $client->sendAudio(
            $setting,
            '081234567890',
            'https://contoh.test/suara.ogg',
            'true',
            'inbox-audio',
        ));

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/messages/document'
            && $request['sessionId'] === self::SESSION_ID
            && $request['url'] === 'https://contoh.test/berkas.pdf'
            && $request['filename'] === 'berkas.pdf'
            && $request['caption'] === 'Lampiran hasil'
            && $request->hasHeader('Idempotency-Key', 'inbox-dok'));
        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/messages/audio'
            && $request['url'] === 'https://contoh.test/suara.ogg'
            && $request['voiceNote'] === true
            && $request->hasHeader('Idempotency-Key', 'inbox-audio'));
    }

    /**
     * Gateway boleh membalas daftar sesi sebagai array polos atau di dalam
     * `{items, meta}`; kedua bentuk itu diterima pemanggil.
     */
    public function test_list_sessions_accepts_plain_and_items_envelope(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => [
            'items' => [['id' => 's1', 'sessionId' => 'klinik-uji']],
            'meta' => ['page' => 1],
        ]], 200)]);

        $this->assertSame(
            'klinik-uji',
            app(WhatsappGatewayClient::class)->listSessions($this->gatewaySetting())[0]['sessionId'],
        );
    }
}
