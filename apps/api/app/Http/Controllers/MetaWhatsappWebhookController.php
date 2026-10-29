<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessInboundMessageJob;
use App\Models\WhatsappSetting;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Pintu masuk pesan WhatsApp Cloud API (Meta).
 *
 * Route-nya publik dan di luar grup {tenant}: Meta tidak mengenal slug klinik.
 * Yang menentukan pesan ini milik klinik mana adalah `phone_number_id` pada
 * payload — nomor itulah yang tiap klinik daftarkan sendiri.
 *
 * Berbeda dengan webhook WAHA yang menjaga lewat token di URL, Meta tidak
 * mengirim token saat pesan masuk. Yang membuktikan kiriman itu benar dari
 * Meta adalah tanda tangan HMAC atas isi mentah permintaan.
 *
 * ponytail: hanya pesan masuk yang ditangani. Balasan chatbot tetap dikirim
 * lewat gateway WAHA (WahaClient), jadi pesan yang masuk lewat Cloud API
 * dibalas lewat jalur lain dan nomor Meta-nya harus terhubung ke sesi WAHA
 * yang sama. Pengiriman keluar lewat Cloud API = pekerjaan terpisah.
 */
class MetaWhatsappWebhookController extends Controller
{
    /** Handshake pendaftaran URL webhook oleh Meta. */
    public function verify(Request $request): Response
    {
        $expected = (string) config('meta_whatsapp.verify_token');

        // Nama parameter aslinya "hub.verify_token", tapi PHP mengubah titik
        // pada nama parameter kueri menjadi garis bawah saat menyalinnya ke
        // $_GET. Keduanya dibaca supaya bentuk apa pun tetap dikenali.
        $mode = $this->query($request, 'hub.mode', 'hub_mode');
        $token = $this->query($request, 'hub.verify_token', 'hub_verify_token');
        $challenge = $this->query($request, 'hub.challenge', 'hub_challenge');

        // Token kosong berarti belum disetel; menerima apa pun dalam keadaan
        // itu sama dengan membuka handshake untuk siapa saja.
        if (blank($expected) || ! hash_equals($expected, $token) || $mode !== 'subscribe') {
            Log::channel('chatbot')->warning('Webhook Meta ditolak: handshake tidak cocok', [
                'mode' => $mode,
            ]);

            return response('', 403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Pesan masuk dari Meta. Selalu 200 untuk payload yang tidak perlu
     * diproses — Meta mengulang kiriman yang tidak dibalas 200, dan mengulang
     * status atau pesan media tidak akan mengubah apa pun.
     */
    public function receive(Request $request): JsonResponse
    {
        if (! $this->signatureIsValid($request)) {
            Log::channel('chatbot')->warning('Webhook Meta ditolak: tanda tangan tidak sah');

            return $this->respond('ditolak', 403);
        }

        $payload = json_decode($request->getContent(), true);

        Log::channel('chatbot')->debug('Payload webhook Meta diterima', [
            'object' => data_get($payload, 'object'),
            'entries' => is_array($payload) ? count((array) data_get($payload, 'entry', [])) : 0,
        ]);

        foreach ((array) data_get($payload, 'entry', []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                $this->handleChange((array) data_get($change, 'value', []));
            }
        }

        return $this->respond('diterima');
    }

    /**
     * Satu perubahan pada satu nomor. `statuses`, media, reaksi, dan kontak
     * tiba pada jalur yang sama tapi tidak punya `messages`, jadi dibiarkan
     * lewat tanpa jawaban.
     *
     * @param  array<string, mixed>  $value
     */
    private function handleChange(array $value): void
    {
        $phoneNumberId = (string) (data_get($value, 'metadata.phone_number_id') ?? '');
        $messages = data_get($value, 'messages');

        if ($phoneNumberId === '' || ! is_array($messages) || $messages === []) {
            return;
        }

        $setting = WhatsappSetting::withoutGlobalScopes()
            ->where('phone_number_id', $phoneNumberId)
            ->first();

        if ($setting === null) {
            Log::channel('chatbot')->warning('Pesan masuk ditolak: nomor Meta tidak dikenal', [
                'phone_number_id' => $phoneNumberId,
            ]);

            return;
        }

        foreach ($messages as $message) {
            $this->queueIfText($setting, (array) $message);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function queueIfText(WhatsappSetting $setting, array $message): void
    {
        // Media belum didukung; membalas gambar dengan tebakan teks lebih buruk
        // daripada tidak membalas sama sekali.
        if (($message['type'] ?? null) !== 'text') {
            Log::channel('chatbot')->debug('Pesan Meta dilewati: bukan pesan teks', [
                'phone_number_id' => $setting->phone_number_id,
                'type' => $message['type'] ?? null,
            ]);

            return;
        }

        $body = data_get($message, 'text.body');

        if (! is_string($body) || trim($body) === '') {
            Log::channel('chatbot')->debug('Pesan Meta dilewati: teks kosong', [
                'phone_number_id' => $setting->phone_number_id,
            ]);

            return;
        }

        $from = $message['from'] ?? null;
        $phone = PhoneNumber::normalize(is_string($from) ? $from : null);

        if ($phone === null) {
            Log::channel('chatbot')->warning('Pesan Meta ditolak: nomor pengirim tidak valid', [
                'phone_number_id' => $setting->phone_number_id,
                'from' => $from,
            ]);

            return;
        }

        Log::channel('chatbot')->info('Pesan Meta diterima, job di-dispatch', [
            'tenant_id' => $setting->tenant_id,
            'phone_number_id' => $setting->phone_number_id,
            'from' => $phone,
            'body' => trim($body),
        ]);

        ProcessInboundMessageJob::dispatch($setting->tenant_id, $phone, trim($body));
    }

    /**
     * Tanda tangan HMAC-SHA256 atas isi mentah permintaan, dibandingkan dalam
     * waktu tetap. Isi mentah dipakai apa adanya — bukan hasil parse ulang —
     * karena satu spasi berbeda saja membuat hash-nya tidak cocok.
     */
    private function signatureIsValid(Request $request): bool
    {
        $secret = (string) config('meta_whatsapp.app_secret');
        $header = (string) $request->header('X-Hub-Signature-256', '');

        if ($secret === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, substr($header, 7));
    }

    /** Parameter kueri dengan nama bertitik, dengan cadangan bentuk garis bawah. */
    private function query(Request $request, string $dotted, string $underscored): string
    {
        $value = $request->query($dotted) ?? $request->query($underscored);

        return is_string($value) ? $value : '';
    }

    private function respond(string $reason, int $status = 200): JsonResponse
    {
        return response()->json(['data' => ['status' => $reason], 'meta' => []], $status);
    }
}
