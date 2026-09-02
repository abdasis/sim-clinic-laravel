<?php

namespace App\Services;

use App\Support\WahaClient;
use App\Support\WahaSessionState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keadaan sambungan WhatsApp klinik, dan tindakan menyalakannya.
 *
 * Sengaja dua hal terpisah. Layar Koneksi menanyakan ulang tiap 4 detik
 * supaya QR yang kedaluwarsa berganti sendiri; kalau menanya sekaligus
 * berarti menyalakan atau memulai ulang sesi, memandangi layar itu sama
 * dengan menggedor gateway belasan kali semenit. Sesi yang sedang membuka
 * WhatsApp Web tidak pernah sempat selesai, dan yang memakainya melihat
 * nomornya "keluar terus".
 */
class WhatsappConnectionService
{
    /**
     * Jarak minimum antar tindakan menyalakan sesi.
     *
     * Menyalakan sesi butuh puluhan detik. Permintaan yang datang lebih rapat
     * dari itu pasti memotong percobaan yang sedang berjalan, jadi ditahan di
     * sisi server — bukan diserahkan pada sopan-santun layarnya, yang tidak
     * bisa dijamin dan tidak terlihat kalau berubah.
     */
    private const SECONDS_BETWEEN_ATTEMPTS = 30;

    /**
     * Keadaan sesi apa adanya. Tidak menyentuh apa pun.
     *
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $client = app(WahaClient::class);

        if ($client === null) {
            return ['available' => false, 'connected' => false, 'qr' => null];
        }

        try {
            $status = $client->sessionStatus();
        } catch (Throwable $e) {
            Log::error('WAHA tidak merespons', ['exception' => $e]);

            return ['available' => true, 'connected' => false, 'qr' => null, 'error' => true];
        }

        $state = WahaSessionState::parse($status['status'] ?? null);

        if ($state === WahaSessionState::Working) {
            return [
                'available' => true,
                'connected' => true,
                'qr' => null,
                'number' => $this->accountNumber($status),
                'name' => $status['me']['pushName'] ?? null,
            ];
        }

        return [
            'available' => true,
            'connected' => false,
            'qr' => $this->qrCode($client, $state),
            'number' => null,
            'name' => null,
            // Sesi yang perlu dinyalakan dulu; layar memakai ini untuk
            // menawarkan tombolnya alih-alih menampilkan QR yang tidak akan
            // pernah terbit.
            'needs_start' => $state->needsAttention(),
        ];
    }

    /**
     * Nyalakan sesinya bila memang perlu, lalu laporkan keadaannya.
     *
     * @return array<string, mixed>
     */
    public function prepare(): array
    {
        $client = app(WahaClient::class);

        if ($client === null) {
            return $this->state();
        }

        try {
            $state = $client->sessionState();

            if ($state !== WahaSessionState::Working && $this->mayAct()) {
                $this->wake($client, $state);
            }
        } catch (Throwable $e) {
            Log::error('Gagal menyiapkan sesi WhatsApp.', ['exception' => $e]);

            return ['available' => true, 'connected' => false, 'qr' => null, 'error' => true];
        }

        return $this->state();
    }

    /** Satu tindakan per sesi per jendela; sisanya cuma dilaporkan. */
    private function mayAct(): bool
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if ($tenant === null) {
            return true;
        }

        return Cache::add('waha:wake:'.$tenant->id, true, self::SECONDS_BETWEEN_ATTEMPTS);
    }

    private function wake(WahaClient $client, WahaSessionState $state): void
    {
        match ($state) {
            WahaSessionState::NotFound => $client->createSession(),
            // Sesi yang gagal perlu dihentikan dulu; menyalakannya begitu saja
            // hanya menabrak sisa percobaan yang menggantung.
            WahaSessionState::Failed => $client->restartSession(),
            default => $client->startSession(),
        };
    }

    /**
     * QR hanya diambil saat sesinya memang sedang menunggu dipindai.
     *
     * Di keadaan lain gateway membalas kosong atau galat, dan memintanya tiap
     * 4 detik cuma menambah lalu lintas yang tidak berujung gambar.
     */
    private function qrCode(WahaClient $client, WahaSessionState $state): ?string
    {
        if ($state !== WahaSessionState::ScanQrCode && $state !== WahaSessionState::Starting) {
            return null;
        }

        try {
            return $client->qrCode();
        } catch (Throwable $e) {
            Log::warning('QR WhatsApp belum bisa diambil.', ['exception' => $e]);

            return null;
        }
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
}
