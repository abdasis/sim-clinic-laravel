<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien WhatsApp Cloud API (Meta) untuk mengirim teks, media, dan kontak.
 *
 * Nomor pengirim (`phone_number_id`) milik tiap klinik, sedangkan token akses
 * milik aplikasi Meta platform. Token tidak pernah dicatat ke log atau
 * disimpan di basis data.
 */
class CloudApiClient
{
    /** Jenis media yang tidak menerima caption menurut Cloud API. */
    private const NO_CAPTION_TYPES = ['audio', 'sticker'];

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiVersion,
        private readonly string $accessToken,
        private readonly string $phoneNumberId,
    ) {}

    /**
     * Kirim satu pesan teks. Mengembalikan id pesan dari Meta bila ada.
     *
     * Dipanggil hanya dari jalur yang sudah memeriksa jendela 24 jam; Meta
     * menolak balasan bebas di luar jendela itu.
     */
    public function sendText(string $phone, string $message, ?string $contextMessageId = null): ?string
    {
        $payload = [
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ];

        if ($contextMessageId !== null && $contextMessageId !== '') {
            $payload['context'] = ['message_id' => $contextMessageId];
        }

        return $this->sendMessage($phone, 'text', $payload);
    }

    /**
     * Beri atau cabut reaksi pada pesan yang sudah ada. Emoji kosong
     * mencabut reaksi sebelumnya — begitu kontrak Cloud API.
     */
    public function sendReaction(string $phone, string $messageId, string $emoji): ?string
    {
        return $this->sendMessage($phone, 'reaction', [
            'reaction' => [
                'message_id' => $messageId,
                'emoji' => $emoji,
            ],
        ]);
    }

    /**
     * Unggah berkas ke Meta, kembalikan id media yang dipakai `sendMedia`.
     *
     * Berkas dikirim mentah sebagai multipart; Meta yang menyimpannya dan
     * memberi id. Id itu hanya berlaku untuk pengiriman berikutnya, bukan
     * url publik — jadi tidak perlu disk yang bisa diakses dari luar.
     */
    public function uploadMedia(string $binary, string $mime, string $filename): string
    {
        $this->ensureConfigured();

        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->timeout(60)
            ->connectTimeout(10)
            ->retry(2, 300, throw: false)
            ->attach('file', $binary, $filename, ['Content-Type' => $mime])
            ->post($this->url('media'), ['messaging_product' => 'whatsapp']);

        if (! $response->successful()) {
            $this->fail('Cloud API Meta menolak unggahan media', $response);
        }

        $id = $response->json('id');

        if (! is_string($id) || $id === '') {
            throw new CloudApiException($response->status(), null, 'Cloud API Meta tidak mengembalikan id media.');
        }

        return $id;
    }

    /**
     * Kirim media yang sudah diunggah. `$type` adalah kunci payload Meta
     * (image, video, audio, document, sticker).
     */
    public function sendMedia(
        string $phone,
        string $type,
        string $mediaId,
        ?string $caption = null,
        ?string $filename = null,
    ): ?string {
        $media = ['id' => $mediaId];

        if ($type === 'document' && $filename !== null && $filename !== '') {
            $media['filename'] = $filename;
        }

        if (! in_array($type, self::NO_CAPTION_TYPES, true) && $caption !== null && trim($caption) !== '') {
            $media['caption'] = $caption;
        }

        return $this->sendMessage($phone, $type, [$type => $media]);
    }

    /**
     * Kirim satu kartu kontak. `$contactPhone` harus sudah berkode negara.
     */
    public function sendContacts(string $phone, string $name, string $contactPhone): ?string
    {
        return $this->sendMessage($phone, 'contacts', [
            'contacts' => [[
                'name' => ['formatted_name' => $name],
                'phones' => [['phone' => $contactPhone, 'type' => 'CELL']],
            ]],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload  bagian pesan selain amplop
     */
    private function sendMessage(string $phone, string $type, array $payload): ?string
    {
        $this->ensureConfigured();

        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->timeout(20)
            ->connectTimeout(10)
            ->retry(2, 300, throw: false)
            ->post($this->url('messages'), [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => $type,
                ...$payload,
            ]);

        if (! $response->successful()) {
            $this->fail('Cloud API Meta menolak pengiriman', $response);
        }

        $id = data_get($response->json(), 'messages.0.id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function ensureConfigured(): void
    {
        if ($this->accessToken === '' || $this->phoneNumberId === '') {
            throw new CloudApiException(0, null, 'Cloud API Meta belum dikonfigurasi.');
        }
    }

    private function fail(string $what, Response $response): never
    {
        $exception = CloudApiException::rejected($what, $response);

        // Konteks sengaja tanpa token: log yang bocor tidak bisa ditarik
        // kembali dari penyimpanan.
        Log::error($what.'.', [
            'phone_number_id' => $this->phoneNumberId,
            'status' => $response->status(),
            'reason' => $exception->reason,
        ]);

        throw $exception;
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/')
            .'/'.trim($this->apiVersion, '/')
            .'/'.$this->phoneNumberId
            .'/'.$path;
    }
}
