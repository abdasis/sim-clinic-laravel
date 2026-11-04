<?php

namespace App\Services;

use App\Actions\Whatsapp\StoreGatewaySessionAction;
use App\Models\WhatsappSetting;
use App\Support\WhatsappGatewayClient;
use App\Support\WhatsappGatewayException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keadaan sesi WhatsApp klinik di gateway internal, dan tindakan atasnya.
 *
 * Membaca dan menyalakan sengaja dipisah. Layar Koneksi menanyakan keadaan
 * tiap 4 detik supaya QR yang kedaluwarsa berganti sendiri; kalau membaca
 * ikut menyalakan sesi, memandangi layar itu sama dengan menggedor gateway
 * belasan kali semenit dan nomornya tidak pernah sempat tersambung.
 */
class WhatsappGatewaySessionService
{
    private const SECONDS_BETWEEN_ATTEMPTS = 30;

    private const STATUS_CONNECTED = 'CONNECTED';

    private const STATUS_QR_REQUIRED = 'QR_REQUIRED';

    public function __construct(
        private readonly WhatsappGatewayClient $client,
        private readonly WhatsappGatewayProvisioningService $provisioning,
        private readonly StoreGatewaySessionAction $storeSession,
    ) {}

    /**
     * Keadaan sesi apa adanya. Tidak membuat atau menyalakan apa pun.
     *
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $setting = $this->setting();

        if ($setting === null || ! $setting->provisioned()) {
            return $this->emptyState(available: false, provisioned: false);
        }

        if (blank($setting->gateway_session_id)) {
            return $this->emptyState(available: true, provisioned: true);
        }

        try {
            $status = $this->client->sessionStatus($setting);
        } catch (Throwable $e) {
            Log::error('Gagal membaca status sesi gateway WhatsApp klinik.', [
                'exception' => $e,
                'tenant_id' => $setting->tenant_id,
            ]);

            return [
                ...$this->emptyState(available: true, provisioned: true),
                'error' => true,
            ];
        }

        $state = $status['status'] ?? null;

        if ($state === self::STATUS_CONNECTED) {
            return [
                'available' => true,
                'connected' => true,
                'qr' => null,
                'number' => $status['phoneNumber'] ?? null,
                'name' => $this->clinicName($setting),
                'provisioned' => true,
            ];
        }

        return [
            'available' => true,
            'connected' => false,
            'qr' => $state === self::STATUS_QR_REQUIRED ? $this->qrCode($setting) : null,
            'number' => null,
            'name' => $this->clinicName($setting),
            'provisioned' => true,
        ];
    }

    /**
     * Siapkan sesi bila perlu, lalu laporkan keadaannya.
     *
     * @return array<string, mixed>
     */
    public function prepare(): array
    {
        $setting = $this->setting();

        if ($setting === null) {
            return $this->state();
        }

        try {
            $this->provisioning->ensure($setting);

            // Satu tindakan per klinik per jendela: menyalakan sesi butuh
            // puluhan detik, dan permintaan yang lebih rapat pasti memotong
            // percobaan yang sedang berjalan.
            $mayAct = $this->mayAct($setting);

            if ($mayAct && blank($setting->gateway_session_id)) {
                $this->ensureSession($setting);
            }

            if ($mayAct && $this->statusOf($setting) !== self::STATUS_CONNECTED) {
                $this->client->connectSession($setting);
            }
        } catch (Throwable $e) {
            Log::error('Gagal menyiapkan sesi gateway WhatsApp klinik.', [
                'exception' => $e,
                'tenant_id' => $setting->tenant_id,
            ]);

            return [
                ...$this->emptyState(available: true, provisioned: $setting->provisioned()),
                'error' => true,
            ];
        }

        return $this->state();
    }

    /**
     * Kode pairing untuk nomor yang sudah dinormalkan FormRequest.
     *
     * @return array{pairing_code: string|null, expires_at: string|null}
     */
    public function pairing(string $phone): array
    {
        $data = $this->client->pairingCode($this->configuredSetting(), $phone);

        return [
            'pairing_code' => $data['pairingCode'] ?? null,
            'expires_at' => $data['expiresAt'] ?? null,
        ];
    }

    public function restart(): void
    {
        $this->client->restartSession($this->configuredSetting());
    }

    public function disconnect(): void
    {
        $this->client->disconnectSession($this->configuredSetting());
    }

    public function logout(): void
    {
        $this->client->logoutSession($this->configuredSetting());
    }

    /** Adopsi sesi berslug sama yang sudah ada di gateway, atau buat baru. */
    private function ensureSession(WhatsappSetting $setting): void
    {
        if (blank($setting->session)) {
            throw new WhatsappGatewayException(
                'SESSION_SLUG_MISSING',
                0,
                null,
                [],
                'Nama sesi WhatsApp klinik belum diisi.',
            );
        }

        foreach ($this->client->listSessions($setting) as $session) {
            if (($session['sessionId'] ?? null) === $setting->session) {
                $this->storeSession->handle($setting, (string) $session['id']);

                return;
            }
        }

        $created = $this->client->createSession($setting, $this->clinicName($setting), $setting->session);
        $id = $created['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new WhatsappGatewayException(
                'SESSION_ID_MISSING',
                0,
                null,
                [],
                'Gateway tidak mengembalikan id sesi WhatsApp.',
            );
        }

        $this->storeSession->handle($setting, $id);
    }

    private function statusOf(WhatsappSetting $setting): ?string
    {
        if (blank($setting->gateway_session_id)) {
            return null;
        }

        return $this->client->sessionStatus($setting)['status'] ?? null;
    }

    private function mayAct(WhatsappSetting $setting): bool
    {
        return Cache::add('gateway:prepare:'.$setting->tenant_id, true, self::SECONDS_BETWEEN_ATTEMPTS);
    }

    /**
     * QR hanya diambil saat sesi memang menunggu dipindai. Di keadaan lain
     * gateway membalas galat, dan memintanya tiap 4 detik cuma menambah lalu
     * lintas yang tidak berujung gambar.
     */
    private function qrCode(WhatsappSetting $setting): ?string
    {
        try {
            $qr = $this->client->qrCode($setting)['qr'] ?? null;

            return is_string($qr) ? $qr : null;
        } catch (Throwable $e) {
            Log::error('QR gateway WhatsApp klinik belum tersedia.', [
                'exception' => $e,
                'tenant_id' => $setting->tenant_id,
            ]);

            return null;
        }
    }

    private function configuredSetting(): WhatsappSetting
    {
        $setting = $this->setting();

        if ($setting === null || ! $setting->isConfigured()) {
            throw new WhatsappGatewayException(
                'SESSION_NOT_CONFIGURED',
                0,
                null,
                [],
                'Sesi WhatsApp klinik belum tersambung. Hubungkan nomor terlebih dahulu.',
            );
        }

        return $setting;
    }

    private function setting(): ?WhatsappSetting
    {
        return WhatsappSetting::query()->first();
    }

    private function clinicName(WhatsappSetting $setting): string
    {
        return $setting->tenant?->name ?: 'Klinik '.$setting->tenant_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyState(bool $available, bool $provisioned): array
    {
        return [
            'available' => $available,
            'connected' => false,
            'qr' => null,
            'number' => null,
            'name' => null,
            'provisioned' => $provisioned,
        ];
    }
}
