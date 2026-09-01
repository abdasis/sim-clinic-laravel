<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Klien WAHA (WhatsApp HTTP API) — satu-satunya penyedia WhatsApp.
 *
 * Alamat dan API key-nya milik pengelola platform (satu server untuk semua
 * klinik); nama sesinya milik tiap klinik. Ketiganya diserahkan saat
 * pembuatan, jadi klien ini tidak perlu tahu soal tenant maupun database.
 *
 * Kirim pesan, urus sesinya, dan daftarkan alamat webhook untuk pesan masuk.
 * Yang menerima pesan masuknya sendiri route webhook, bukan kelas ini.
 */
class WahaClient
{
    /**
     * Sumber pesan yang tidak pernah boleh sampai ke aplikasi: status/story,
     * grup, saluran, dan daftar siaran. Semuanya bukan percakapan berdua.
     */
    private const IGNORED_SOURCES = [
        'status' => true,
        'groups' => true,
        'channels' => true,
        'broadcast' => true,
    ];

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $session,
    ) {}

    /**
     * Kirim satu pesan. Melempar bila gagal — pemanggil yang memutuskan
     * pengulangan dan pencatatan statusnya.
     */
    public function send(string $phone, string $message): void
    {
        $chatId = $this->chatId($phone);

        if ($chatId === null) {
            throw new RuntimeException('Nomor tujuan tidak valid: '.$phone);
        }

        $response = $this->request()->post($this->url('/api/sendText'), [
            'session' => $this->session,
            'chatId' => $chatId,
            'text' => $message,
        ]);

        if (! $response->successful()) {
            throw WahaException::rejected('WAHA menolak pengiriman', $response);
        }
    }

    /**
     * Kirim satu gambar berikut keterangannya. Melempar bila gagal, sama
     * seperti send().
     *
     * Berkasnya dikirim sebagai base64, bukan url. WAHA yang menjemput url
     * mengharuskan servernya bisa menjangkau alamat publik aplikasi — asumsi
     * yang diam-diam runtuh di jaringan internal, dan runtuhnya terbaca
     * sebagai "gambar tidak sampai" tanpa petunjuk apa pun.
     *
     * ponytail: satu gambar disandikan ulang untuk tiap penerima, jadi blast
     * ratusan pesan mengirim berkas yang sama berkali-kali ke gateway. Cukup
     * untuk ukuran poster klinik (dibatasi 2 MB di FormRequest); bila daftar
     * penerima tumbuh jauh lebih besar, unggah sekali ke WAHA lalu kirim
     * ulang lewat id medianya.
     */
    public function sendImage(string $phone, string $binary, string $mimeType, string $filename, string $caption = ''): void
    {
        $chatId = $this->chatId($phone);

        if ($chatId === null) {
            throw new RuntimeException('Nomor tujuan tidak valid: '.$phone);
        }

        $response = $this->request()->post($this->url('/api/sendImage'), [
            'session' => $this->session,
            'chatId' => $chatId,
            'file' => [
                'mimetype' => $mimeType,
                'filename' => $filename,
                'data' => base64_encode($binary),
            ],
            'caption' => $caption,
        ]);

        if (! $response->successful()) {
            throw WahaException::rejected('WAHA menolak pengiriman gambar', $response);
        }
    }

    /**
     * Nyalakan indikator "sedang mengetik" di layar pasien.
     *
     * Sengaja tidak pernah melempar. Indikator ini penyempurnaan rasa, bukan
     * fungsi inti: engine WAHA tertentu bisa menolaknya, dan menjatuhkan
     * seluruh balasan hanya karena animasi titik-titik gagal muncul adalah
     * tukar-tambah yang salah. Kegagalannya tercatat, tidak ditelan.
     */
    public function startTyping(string $phone): void
    {
        $this->typing('/api/startTyping', $phone, ['typing' => true]);
    }

    /** Matikan indikator. Sama seperti startTyping, tidak pernah melempar. */
    public function stopTyping(string $phone): void
    {
        $this->typing('/api/stopTyping', $phone);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function typing(string $path, string $phone, array $extra = []): void
    {
        $chatId = $this->chatId($phone);

        if ($chatId === null) {
            Log::warning('Indikator mengetik dilewati: nomor tujuan tidak valid.', ['phone' => $phone]);

            return;
        }

        try {
            $response = $this->request()->post($this->url($path), [
                'session' => $this->session,
                'chatId' => $chatId,
            ] + $extra);

            if (! $response->successful()) {
                Log::warning('WAHA menolak indikator mengetik.', [
                    'path' => $path,
                    'phone' => $phone,
                    'status' => $response->status(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Indikator mengetik gagal dikirim.', [
                'exception' => $e,
                'path' => $path,
                'phone' => $phone,
            ]);
        }
    }

    /** Alamat chat WAHA dari nomor telepon, atau null bila nomornya tidak sah. */
    private function chatId(string $phone): ?string
    {
        $normalized = PhoneNumber::normalize($phone);

        return $normalized === null ? null : $normalized.'@c.us';
    }

    /**
     * Keadaan sesi. 404 (belum dibuat) tidak dianggap error — balik
     * NOT_FOUND agar pemanggil bisa membuatnya. Error lain tetap dilempar.
     *
     * @return array<string, mixed>
     */
    public function sessionStatus(): array
    {
        $response = $this->request()
            ->get($this->url('/api/sessions/'.$this->session));

        if ($response->status() === 404) {
            return ['status' => 'NOT_FOUND'];
        }

        return (array) $response->throw()->json();
    }

    /**
     * Sesi benar-benar siap mengirim?
     *
     * WAHA menerima permintaan kirim selama sesinya ada, tapi pesan hanya
     * benar-benar berangkat kalau statusnya WORKING. Dipakai sebagai
     * penjagaan sebelum ratusan pesan diantrekan — sesi yang terputus
     * menghanguskan seluruh campaign satu per satu.
     */
    public function isConnected(): bool
    {
        return ($this->sessionStatus()['status'] ?? null) === 'WORKING';
    }

    /** Idempoten di sisi WAHA: sesi yang sudah jalan tidak dimulai dua kali. */
    public function startSession(): void
    {
        $this->request()
            ->post($this->url('/api/sessions/'.$this->session.'/start'))
            ->throw();
    }

    /** Buat sesi WAHA bila belum ada. 409 (sudah ada) dianggap sukses. */
    public function createSession(): void
    {
        $response = $this->request()
            ->post($this->url('/api/sessions'), ['name' => $this->session]);

        if ($response->status() !== 409) {
            $response->throw();
        }
    }

    /**
     * Mulai ulang sesi yang gagal: hentikan tanpa logout (nomor terhubung
     * tidak hilang), lalu jalankan lagi. Stop yang balas 404/409 diabaikan —
     * sesi memang sudah berhenti — tapi start tetap dijalankan.
     */
    public function restartSession(): void
    {
        try {
            $this->stopSession(false);
        } catch (RequestException $e) {
            if (! in_array($e->response->status(), [404, 409], true)) {
                throw $e;
            }
        }

        $this->startSession();
    }

    /** Hentikan sesi. logout=true memutus nomor; false hanya menghentikan. */
    private function stopSession(bool $logout): void
    {
        $this->request()
            ->post($this->url('/api/sessions/stop'), [
                'name' => $this->session,
                'logout' => $logout,
            ])
            ->throw();
    }

    public function qrCode(): ?string
    {
        $response = $this->request()
            ->get($this->url('/api/'.$this->session.'/auth/qr'));

        if (! $response->successful() || $response->body() === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($response->body());
    }

    public function logout(): void
    {
        $this->stopSession(true);
    }

    /**
     * Daftarkan alamat webhook pesan masuk pada sesi ini.
     *
     * WAHA menyimpan webhook sebagai bagian dari config sesi, jadi ini menimpa
     * seluruh daftarnya — bukan menambah. Itu memang yang dikehendaki: satu
     * sesi milik satu klinik, dan satu klinik hanya punya satu alamat webhook.
     *
     * Sekaligus memasang saringan bawaan gateway. Status, grup, saluran, dan
     * daftar siaran dihentikan sebelum sempat menjadi permintaan HTTP ke
     * aplikasi — lalu lintas yang tidak pernah datang tidak bisa lolos
     * saringan mana pun.
     */
    public function setWebhook(string $url): void
    {
        $response = $this->request()->put($this->url('/api/sessions/'.$this->session), [
            'config' => [
                'webhooks' => [
                    ['url' => $url, 'events' => ['message']],
                ],
                'ignore' => self::IGNORED_SOURCES,
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('WAHA menolak pendaftaran webhook: HTTP '.$response->status());
        }

        // ponytail: keberhasilan pendaftaran tidak menjamin saringannya
        // tersimpan — versi WAHA yang belum mengenal `config.ignore` menerima
        // permintaannya lalu mengabaikan bagian itu diam-diam. Yang bisa
        // dilakukan sekarang hanya mencatat config yang benar-benar tersimpan
        // supaya selisihnya terlihat saat ditelusuri. Ceiling-nya di situ:
        // aplikasi tidak tahu apakah gateway menurut. Upgrade path-nya
        // memeriksa versi lewat GET /api/version dan menolak menyalakan
        // chatbot pada versi yang tidak mendukungnya. Sampai itu ada, lapis
        // kedua di InboundMessageController yang menjaga.
        $this->logIgnoreSupport($response->json());
    }

    /**
     * Catat apakah saringan bawaan benar-benar tersimpan di sesi.
     *
     * @param  mixed  $session
     */
    private function logIgnoreSupport($session): void
    {
        $stored = data_get($session, 'config.ignore');

        if (blank($stored)) {
            Log::channel('chatbot')->warning('Gateway tidak menyimpan saringan bawaan; saringan aplikasi yang menjaga.', [
                'session' => $this->session,
                'expected' => self::IGNORED_SOURCES,
            ]);

            return;
        }

        Log::channel('chatbot')->info('Saringan bawaan gateway terpasang.', [
            'session' => $this->session,
            'ignore' => $stored,
        ]);
    }

    private function request(): PendingRequest
    {
        return Http::timeout(20)->withHeaders(['X-Api-Key' => $this->apiKey]);
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}
