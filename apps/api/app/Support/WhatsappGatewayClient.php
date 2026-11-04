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
 * WhatsappSetting. Kelas ini tidak membaca database maupun memegang tenant.
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

    /**
     * Kredensial yang membuka nomor WhatsApp klinik. Bila salah satunya
     * kosong, klinik memang belum terhubung — bukan galat sesaat.
     *
     * @return array<string, string>
     */
    private function clinicHeaders(WhatsappSetting $setting): array
    {
        $tenantKey = $setting->gateway_tenant_key;
        $apiKey = $setting->gateway_api_key;

        if (blank($tenantKey) || blank($apiKey)) {
            throw new WhatsappGatewayException(
                'GATEWAY_NOT_PROVISIONED',
                0,
                null,
                [],
                'Kredensial gateway klinik belum lengkap. Hubungkan WhatsApp terlebih dahulu.',
            );
        }

        return [
            'x-tenant-key' => $tenantKey,
            'X-API-Key' => $apiKey,
        ];
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

        if (! $response->successful() || ! is_array($json) || ($json['success'] ?? false) !== true) {
            $exception = WhatsappGatewayException::fromResponse($response);

            Log::error('Gateway WhatsApp menolak permintaan.', [
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
