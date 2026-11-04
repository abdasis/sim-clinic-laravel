<?php

namespace App\Support;

use App\Models\WhatsappSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Klien HTTP gateway WhatsApp internal.
 *
 * Alamat servernya satu untuk semua klinik (whatsapp_gateway_settings), tetapi
 * kredensialnya milik tiap klinik dan diserahkan per panggilan lewat
 * WhatsappSetting. Kelas ini tidak memegang tenant; satu-satunya data yang
 * dibacanya adalah jatah pesan harian, lewat DailyMessageQuota.
 *
 * Semua jawaban dibaca sebagai envelope `{success, message, data}`; hanya isi
 * `data` yang dikembalikan. Penolakan apa pun menjadi
 * WhatsappGatewayException yang membawa kode, status, dan Retry-After.
 */
class WhatsappGatewayClient
{
    private const TIMEOUT_SECONDS = 15;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    private readonly string $baseUrl;

    /**
     * `$baseUrl` dinormalkan di sini supaya pemanggil tidak perlu tahu bahwa
     * setiap endpoint diawali `/api/v1`: pengaturan central boleh diisi
     * dengan maupun tanpa akhiran itu.
     */
    public function __construct(string $baseUrl)
    {
        $baseUrl = rtrim(trim($baseUrl), '/');

        if (str_ends_with($baseUrl, '/api/v1')) {
            $baseUrl = rtrim(substr($baseUrl, 0, -strlen('/api/v1')), '/');
        }

        $this->baseUrl = $baseUrl;
    }

    /**
     * Daftarkan klinik sebagai tenant gateway, lalu kembalikan kredensialnya.
     *
     * @return array{tenant_key: string, access_token: string, refresh_token: string}
     */
    public function register(string $name, string $organization, string $email, string $password): array
    {
        return $this->normalizeAuth($this->send('post', '/auth/register', [
            'name' => $name,
            'organizationName' => $organization,
            'email' => $email,
            'password' => $password,
        ]));
    }

    /**
     * Masuk sebagai tenant gateway yang sudah terdaftar. Dipakai saat
     * provisioning terputus di tengah jalan.
     *
     * @return array{tenant_key: string, access_token: string, refresh_token: string}
     */
    public function login(string $email, string $password): array
    {
        return $this->normalizeAuth($this->send('post', '/auth/login', [
            'email' => $email,
            'password' => $password,
        ]));
    }

    /**
     * Buat API key klinik. Autentikasinya memakai access token hasil
     * register/login, bukan API key — itulah satu-satunya langkah yang
     * masih berjalan sebelum API key-nya ada.
     */
    public function createApiKey(string $tenantKey, string $accessToken, string $name): string
    {
        $data = $this->send('post', '/api-keys', ['name' => $name], [
            'Authorization' => 'Bearer '.$accessToken,
            'x-tenant-key' => $tenantKey,
        ]);

        $key = $data['key'] ?? null;

        if (! is_string($key) || $key === '') {
            throw new WhatsappGatewayException(
                'API_KEY_MISSING',
                0,
                null,
                [],
                'Gateway tidak mengembalikan API key untuk klinik.',
            );
        }

        return $key;
    }

    /** @return array<string, mixed> */
    public function sessionStatus(WhatsappSetting $setting): array
    {
        return $this->send('get', '/sessions/'.$this->gatewaySessionId($setting).'/status', [], $this->clinicHeaders($setting));
    }

    /**
     * Semua sesi milik klinik. Gateway membalas `data` berisi daftar sesi;
     * bentuk `{items, meta}` juga diterima supaya pemanggil tidak perlu tahu
     * versi gateway yang menjawab.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listSessions(WhatsappSetting $setting): array
    {
        $data = $this->send('get', '/sessions', [], $this->clinicHeaders($setting));

        $items = $data['items'] ?? null;

        return is_array($items) ? array_values($items) : array_values($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function createSession(WhatsappSetting $setting, string $name, string $slug): array
    {
        return $this->send('post', '/sessions', ['name' => $name, 'sessionId' => $slug], $this->clinicHeaders($setting));
    }

    /** @return array<string, mixed> */
    public function connectSession(WhatsappSetting $setting): array
    {
        return $this->send('post', '/sessions/'.$this->gatewaySessionId($setting).'/connect', [], $this->clinicHeaders($setting));
    }

    /** @return array<string, mixed> */
    public function qrCode(WhatsappSetting $setting): array
    {
        return $this->send('get', '/sessions/'.$this->gatewaySessionId($setting).'/qr', [], $this->clinicHeaders($setting));
    }

    /** @return array<string, mixed> */
    public function pairingCode(WhatsappSetting $setting, string $phone): array
    {
        return $this->send('post', '/sessions/'.$this->gatewaySessionId($setting).'/pairing-code', [
            'phoneNumber' => $phone,
        ], $this->clinicHeaders($setting));
    }

    public function restartSession(WhatsappSetting $setting): void
    {
        $this->send('post', '/sessions/'.$this->gatewaySessionId($setting).'/restart', [], $this->clinicHeaders($setting));
    }

    public function disconnectSession(WhatsappSetting $setting): void
    {
        $this->send('post', '/sessions/'.$this->gatewaySessionId($setting).'/disconnect', [], $this->clinicHeaders($setting));
    }

    public function logoutSession(WhatsappSetting $setting): void
    {
        $this->send('post', '/sessions/'.$this->gatewaySessionId($setting).'/logout', [], $this->clinicHeaders($setting));
    }

    public function deleteSession(WhatsappSetting $setting): void
    {
        $this->send('delete', '/sessions/'.$this->gatewaySessionId($setting), [], $this->clinicHeaders($setting));
    }

    /**
     * Kirim teks. Kunci idempotensi wajib: pengulangan job tidak boleh
     * mengirim pesan yang sama dua kali ke pasien.
     */
    public function sendText(WhatsappSetting $setting, string $phone, string $text, string $idempotencyKey): ?string
    {
        return $this->messageId($this->send('post', '/messages/send', [
            'sessionId' => $this->gatewaySessionId($setting),
            'to' => $phone,
            'text' => $text,
        ], $this->clinicHeaders($setting), $idempotencyKey));
    }

    public function sendImage(
        WhatsappSetting $setting,
        string $phone,
        string $url,
        ?string $caption,
        string $idempotencyKey,
    ): ?string {
        $body = [
            'sessionId' => $this->gatewaySessionId($setting),
            'to' => $phone,
            'url' => $url,
        ];

        if ($caption !== null && trim($caption) !== '') {
            $body['caption'] = $caption;
        }

        return $this->messageId($this->send(
            'post',
            '/messages/image',
            $body,
            $this->clinicHeaders($setting),
            $idempotencyKey,
        ));
    }

    public function sendDocument(
        WhatsappSetting $setting,
        string $phone,
        string $url,
        ?string $filename,
        ?string $caption,
        string $idempotencyKey,
    ): ?string {
        $body = [
            'sessionId' => $this->gatewaySessionId($setting),
            'to' => $phone,
            'url' => $url,
        ];

        if ($filename !== null && trim($filename) !== '') {
            $body['filename'] = $filename;
        }

        if ($caption !== null && trim($caption) !== '') {
            $body['caption'] = $caption;
        }

        return $this->messageId($this->send(
            'post',
            '/messages/document',
            $body,
            $this->clinicHeaders($setting),
            $idempotencyKey,
        ));
    }

    /**
     * `$voiceNote` diterima sebagai teks karena pemanggil membacanya dari
     * kolom media; gateway sendiri menuntut boolean, jadi dinormalkan di sini.
     */
    public function sendAudio(
        WhatsappSetting $setting,
        string $phone,
        string $url,
        string $voiceNote,
        string $idempotencyKey,
    ): ?string {
        return $this->messageId($this->send('post', '/messages/audio', [
            'sessionId' => $this->gatewaySessionId($setting),
            'to' => $phone,
            'url' => $url,
            'voiceNote' => filter_var($voiceNote, FILTER_VALIDATE_BOOLEAN),
        ], $this->clinicHeaders($setting), $idempotencyKey));
    }

    /**
     * Tanda sedang mengetik/merekam. Kegagalan di sini tidak boleh
     * menggagalkan pengiriman pesan, jadi ditelan setelah dicatat.
     */
    public function presence(WhatsappSetting $setting, string $presence, ?string $phone = null): void
    {
        $body = ['presence' => $presence];

        if ($phone !== null && trim($phone) !== '') {
            $body['to'] = $phone;
        }

        try {
            $this->send(
                'post',
                '/sessions/'.$this->gatewaySessionId($setting).'/presence',
                $body,
                $this->clinicHeaders($setting),
            );
        } catch (Throwable $e) {
            Log::warning('Gagal mengirim status presence ke gateway WhatsApp.', [
                'exception' => $e,
                'presence' => $presence,
            ]);
        }
    }

    /**
     * Daftarkan webhook pesan masuk. Rahasianya hanya dikembalikan gateway
     * sekali saat pembuatan; pemanggil wajib menyimpannya.
     *
     * @param  array<int, string>  $events
     * @return array{id: string, secret: string}
     */
    public function createWebhook(string $tenantKey, string $apiKey, string $url, array $events): array
    {
        $data = $this->send('post', '/webhooks', [
            'name' => (string) config('app.name'),
            'url' => $url,
            'events' => array_values($events),
        ], [
            'x-tenant-key' => $tenantKey,
            'X-API-Key' => $apiKey,
        ]);

        $secret = $data['secret'] ?? null;

        if (! is_string($secret) || $secret === '') {
            throw new WhatsappGatewayException(
                'WEBHOOK_SECRET_MISSING',
                0,
                null,
                [],
                'Gateway tidak mengembalikan rahasia webhook untuk klinik.',
            );
        }

        return ['id' => (string) ($data['id'] ?? ''), 'secret' => $secret];
    }

    /**
     * Kredensial yang membuka nomor WhatsApp klinik. API key wajib; tenant key
     * hanya pelengkap lama — dokumentasi gateway cukup mengenal API key, dan
     * klinik boleh mengisinya manual tanpa alur register.
     *
     * @return array<string, string>
     */
    private function clinicHeaders(WhatsappSetting $setting): array
    {
        $apiKey = $setting->gateway_api_key;

        if (blank($apiKey)) {
            throw new WhatsappGatewayException(
                'GATEWAY_NOT_PROVISIONED',
                0,
                null,
                [],
                'Kredensial gateway klinik belum lengkap. Hubungkan WhatsApp terlebih dahulu.',
            );
        }

        $headers = ['X-API-Key' => $apiKey];

        if (filled($setting->gateway_tenant_key)) {
            $headers['x-tenant-key'] = $setting->gateway_tenant_key;
        }

        return $headers;
    }

    private function gatewaySessionId(WhatsappSetting $setting): string
    {
        $sessionId = $setting->gateway_session_id;

        if (blank($sessionId)) {
            throw new WhatsappGatewayException(
                'SESSION_NOT_CONFIGURED',
                0,
                null,
                [],
                'Sesi WhatsApp klinik belum tersedia di gateway.',
            );
        }

        return $sessionId;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{tenant_key: string, access_token: string, refresh_token: string}
     */
    private function normalizeAuth(array $data): array
    {
        // Register membalas `tenant.key`, login membalas `tenantKey` datar.
        $tenantKey = data_get($data, 'tenant.key') ?? data_get($data, 'tenantKey');
        $accessToken = data_get($data, 'token.accessToken');
        $refreshToken = data_get($data, 'token.refreshToken');

        if (! is_string($tenantKey) || $tenantKey === '' || ! is_string($accessToken) || $accessToken === '') {
            throw new WhatsappGatewayException(
                'AUTH_RESPONSE_INVALID',
                0,
                null,
                [],
                'Gateway tidak mengembalikan kredensial tenant yang lengkap.',
            );
        }

        return [
            'tenant_key' => $tenantKey,
            'access_token' => $accessToken,
            'refresh_token' => is_string($refreshToken) ? $refreshToken : '',
        ];
    }

    /** @param array<string, mixed> $data */
    private function messageId(array $data): ?string
    {
        $id = $data['messageId'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Kirim satu permintaan dan kembalikan isi `data`-nya.
     *
     * `->throw()` sengaja tidak dipakai: badan galat gateway memuat kode dan
     * Retry-After yang hanya bisa dibaca dari respons utuh.
     *
     * @param  'get'|'post'|'delete'  $method
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function send(
        string $method,
        string $path,
        array $body = [],
        array $headers = [],
        ?string $idempotencyKey = null,
    ): array {
        // Seluruh endpoint /messages/* sama-sama mengeluarkan pesan dari nomor
        // klinik, jadi jatah harian diperiksa di satu pintu ini. Panggilan sesi
        // (status, QR, connect) tidak menghitung dan tidak ikut ditahan.
        if (str_starts_with($path, '/messages/')) {
            $this->assertQuotaAvailable();
        }

        $url = $this->baseUrl.'/api/v1'.$path;

        try {
            $response = $this->request($headers, $idempotencyKey)->{$method}($url, $body);
        } catch (Throwable $e) {
            // Header kredensial tidak ikut dicatat: log yang bocor tidak bisa
            // ditarik kembali dari penyimpanan.
            Log::error('Permintaan ke gateway WhatsApp gagal dikirim.', [
                'exception' => $e,
                'path' => $path,
            ]);

            throw WhatsappGatewayException::network($e);
        }

        return $this->envelope($response, $method.' '.$path);
    }

    /**
     * Tahan pengiriman begitu jatah harian habis.
     *
     * Diperiksa tepat sebelum request berangkat, bukan saat pengiriman
     * diantrekan: jatah yang tersisa pagi bisa habis sore karena jalur lain
     * (balasan inbox, pengingat, campaign lain) memakai nomor yang sama.
     */
    private function assertQuotaAvailable(): void
    {
        $quota = DailyMessageQuota::today();

        if (! $quota->isSpent()) {
            return;
        }

        throw new WhatsappGatewayException(
            'DAILY_LIMIT_REACHED',
            429,
            $this->secondsUntilTomorrow(),
            ['limit' => $quota->limit, 'used' => $quota->used],
            __('broadcast.daily_limit_reached', ['limit' => $quota->limit]),
        );
    }

    /**
     * Detik sampai hari berganti, saat jatah kirimnya penuh lagi.
     *
     * Lewat sedikit dari tengah malam, bukan tepat: hitungan hari yang
     * sepersekian detik lebih dulu bisa masih membebani jatah kemarin.
     */
    private function secondsUntilTomorrow(): int
    {
        return (int) now()->diffInSeconds(now()->addDay()->startOfDay()) + 300;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function request(array $headers, ?string $idempotencyKey): PendingRequest
    {
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return Http::withHeaders($headers)
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS);
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(Response $response, string $what): array
    {
        // 204 No Content (restart/logout/hapus sesi) tidak membawa envelope.
        if ($response->successful() && trim($response->body()) === '') {
            return [];
        }

        $json = $response->json();

        if (! $response->successful()) {
            $exception = WhatsappGatewayException::fromResponse($response);

            Log::error('Gateway WhatsApp menolak permintaan.', [
                'request' => $what,
                'status' => $response->status(),
                'code' => $exception->code,
            ]);

            throw $exception;
        }

        if (! is_array($json) || ($json['success'] ?? false) !== true) {
            // 2xx tanpa envelope (halaman proxy, badan terpotong) bukan
            // keberhasilan, tapi juga bukan penolakan yang bisa dibaca.
            // Statusnya dipetakan ke 502: membiarkannya 200 membuat pemanggil
            // menyangka pesannya berangkat.
            $exception = new WhatsappGatewayException(
                'INVALID_RESPONSE',
                502,
                null,
                [],
                'Gateway WhatsApp membalas tanpa envelope yang bisa dibaca (HTTP '.$response->status().').',
            );

            Log::error('Gateway WhatsApp membalas di luar kontrak envelope.', [
                'request' => $what,
                'status' => $response->status(),
                'code' => $exception->code,
            ]);

            throw $exception;
        }

        $data = $json['data'] ?? [];

        return is_array($data) ? $data : [];
    }
}
