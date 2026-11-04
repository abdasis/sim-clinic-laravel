<?php

namespace App\Http\Controllers;

use App\Actions\Inbox\RecordInboundMessageAction;
use App\Actions\Inbox\UpdateMessageStatusAction;
use App\Enums\ConversationChannel;
use App\Enums\MessageStatus;
use App\Jobs\ProcessInboundMessageJob;
use App\Models\WhatsappSetting;
use App\Support\PhoneNumber;
use App\Support\WhatsappGatewayWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Pintu masuk webhook gateway WhatsApp.
 *
 * Route-nya publik dan di luar grup {tenant}: gateway tidak mengenal slug
 * klinik. Yang memetakan pesan ke klinik adalah token pada ruas URL, yang
 * dicari di `whatsapp_settings`; setelah itu tanda tangan HMAC diverifikasi
 * dengan rahasia klinik tersebut.
 *
 * Balasannya selalu cepat dan selalu 200 untuk payload yang tidak perlu
 * diproses. Gateway akan mengirim ulang apa pun yang tidak dibalas 200, jadi
 * keadaan "memang tidak perlu diproses" tidak boleh dijawab dengan galat.
 */
class WhatsappGatewayWebhookController extends Controller
{
    /** Event status kirim beserta status yang diwakilinya. */
    private const STATUS_BY_EVENT = [
        'message.sent' => MessageStatus::Sent,
        'message.delivered' => MessageStatus::Delivered,
        'message.read' => MessageStatus::Read,
        'message.failed' => MessageStatus::Failed,
    ];

    public function store(Request $request, string $token): JsonResponse
    {
        $setting = WhatsappSetting::withoutGlobalScopes()
            ->where('gateway_webhook_token', $token)
            ->first();

        // Token kosong di basis data tidak mungkin cocok dengan ruas URL yang
        // tidak kosong, jadi kasus itu sudah tertutup oleh pencarian di atas.
        if ($setting === null) {
            // Token utuh adalah rahasia yang membuka pintu klinik: cukup
            // awalan pendek dan sidik jarinya untuk menelusuri asalnya.
            // Balasannya tetap 200 — gateway akan mengirim ulang apa pun yang
            // tidak dibalas 200, dan token asing tidak akan menjadi dikenal
            // karena diulang.
            Log::channel('chatbot')->warning('Webhook gateway ditolak: token tidak dikenal', [
                'token_prefix' => mb_substr($token, 0, 6),
                'token_hash' => hash('sha256', $token),
                'alasan' => 'tidak ada pengaturan klinik dengan token ini',
            ]);

            return $this->respond('token tidak dikenal');
        }

        $rawBody = $request->getContent();
        $timestamp = (string) $request->header('X-Webhook-Timestamp', '');

        if (! WhatsappGatewayWebhook::signatureIsValid(
            (string) $setting->gateway_webhook_secret,
            $timestamp,
            $rawBody,
            $request->header('X-Webhook-Signature'),
        )) {
            Log::channel('chatbot')->warning('Webhook gateway ditolak: tanda tangan tidak sah', [
                'tenant_id' => $setting->tenant_id,
                'timestamp' => $timestamp,
            ]);

            return $this->respond('ditolak', 403);
        }

        $event = (string) $request->header('X-Webhook-Event', '');
        $payload = json_decode($rawBody, true);
        $payload = is_array($payload) ? $payload : [];

        return match ($event) {
            'message.received' => $this->receive($setting, $payload),
            'message.sent',
            'message.delivered',
            'message.read',
            'message.failed' => $this->updateStatus($setting, $event, $payload),
            default => $this->ignore($setting, $event, $payload),
        };
    }

    /**
     * Satu pesan masuk. Hanya teks dari percakapan berdua yang diteruskan ke
     * kotak masuk dan chatbot.
     *
     * @param  array<string, mixed>  $payload
     */
    private function receive(WhatsappSetting $setting, array $payload): JsonResponse
    {
        $type = (string) ($payload['type'] ?? '');

        // Media belum didukung; membalas gambar dengan tebakan teks lebih buruk
        // daripada tidak membalas sama sekali.
        if (strtoupper($type) !== 'TEXT') {
            Log::channel('chatbot')->debug('Pesan gateway dilewati: bukan pesan teks', [
                'tenant_id' => $setting->tenant_id,
                'type' => $type,
            ]);

            return $this->respond('bukan pesan teks');
        }

        $body = $payload['content'] ?? null;

        if (! is_string($body) || trim($body) === '') {
            Log::channel('chatbot')->debug('Pesan gateway dilewati: teks kosong', [
                'tenant_id' => $setting->tenant_id,
            ]);

            return $this->respond('teks kosong');
        }

        // Status, grup, saluran, dan daftar siaran tiba lewat webhook yang
        // sama. Tanpa saringan ini, orang yang memasang status berisi kabar
        // duka akan menerima balasan pribadi dari klinik padahal ia tidak
        // pernah mengirim pesan apa pun.
        $remoteJid = (string) ($payload['remoteJid'] ?? '');

        if (! $this->isDirectChat($remoteJid)) {
            Log::channel('chatbot')->warning('Pesan gateway ditolak: bukan percakapan berdua', [
                'tenant_id' => $setting->tenant_id,
                'remoteJid' => $remoteJid,
            ]);

            return $this->respond('bukan percakapan berdua');
        }

        $phone = $this->senderPhone($remoteJid, $payload['remoteJidAlt'] ?? null);

        if ($phone === null) {
            Log::channel('chatbot')->warning('Pesan gateway ditolak: nomor pengirim tidak valid', [
                'tenant_id' => $setting->tenant_id,
                'remoteJid' => $remoteJid,
                'remoteJidAlt' => $payload['remoteJidAlt'] ?? null,
            ]);

            return $this->respond('nomor pengirim tidak valid');
        }

        $providerMessageId = $payload['messageId'] ?? null;
        $body = trim($body);

        $recorded = app(RecordInboundMessageAction::class)->handle(
            $setting->tenant_id,
            $phone,
            $body,
            ConversationChannel::Gateway,
            is_string($providerMessageId) && $providerMessageId !== '' ? $providerMessageId : null,
        );

        // Kiriman ulang tidak boleh memicu balasan chatbot kedua kali.
        if (! $recorded['created']) {
            Log::channel('chatbot')->debug('Pesan gateway dilewati: sudah pernah diterima', [
                'tenant_id' => $setting->tenant_id,
                'from' => $phone,
                'message_id' => $providerMessageId,
            ]);

            return $this->respond('sudah diterima');
        }

        ProcessInboundMessageJob::dispatch(
            $setting->tenant_id,
            $phone,
            $body,
            ConversationChannel::Gateway,
        );

        // Isi pesan pasien tidak ikut dicatat di level info; cukup metadatanya.
        Log::channel('chatbot')->info('Pesan gateway diterima, job di-dispatch', [
            'tenant_id' => $setting->tenant_id,
            'from' => $phone,
            'message_id' => $providerMessageId,
        ]);

        return $this->respond('diterima');
    }

    /**
     * Status kirim satu pesan keluar. Status hanya boleh maju; aturan itu
     * dijaga UpdateMessageStatusAction.
     *
     * @param  array<string, mixed>  $payload
     */
    private function updateStatus(WhatsappSetting $setting, string $event, array $payload): JsonResponse
    {
        $messageId = $payload['messageId'] ?? null;

        if (! is_string($messageId) || $messageId === '') {
            Log::channel('chatbot')->debug('Status gateway dilewati: id pesan tidak ada', [
                'tenant_id' => $setting->tenant_id,
                'event' => $event,
            ]);

            return $this->respond('id pesan tidak ada');
        }

        $message = app(UpdateMessageStatusAction::class)->handle(
            $setting->tenant_id,
            $messageId,
            self::STATUS_BY_EVENT[$event],
        );

        // Status untuk pesan yang tidak ada di kotak masuk bukan kegagalan
        // gateway; cukup diakui, tetapi alasannya harus akurat.
        return $this->respond($message === null ? 'pesan tidak ditemukan' : 'status diperbarui');
    }

    /**
     * Event yang tidak ditangani — mis. `session.*` atau `presence.updated` —
     * cukup diakui. Status koneksi dibaca saat layar memintanya.
     *
     * @param  array<string, mixed>  $payload
     */
    private function ignore(WhatsappSetting $setting, string $event, array $payload): JsonResponse
    {
        Log::channel('chatbot')->debug('Event gateway diabaikan', [
            'tenant_id' => $setting->tenant_id,
            'event' => $event,
            'keys' => array_keys($payload),
        ]);

        return $this->respond('diabaikan');
    }

    /** Percakapan berdua, bukan grup/saluran/siaran. */
    private function isDirectChat(string $jid): bool
    {
        if ($jid === '') {
            return false;
        }

        // Payload lama menyebut nomor tanpa domain sama sekali. Itu tidak
        // pernah berarti ruang bersama, jadi diloloskan.
        if (! str_contains($jid, '@')) {
            return true;
        }

        foreach (['@g.us', '@broadcast', '@newsletter'] as $suffix) {
            if (str_ends_with($jid, $suffix)) {
                return false;
            }
        }

        return str_ends_with($jid, '@c.us')
            || str_ends_with($jid, '@s.whatsapp.net')
            || str_ends_with($jid, '@lid');
    }

    /**
     * Nomor pengirim di balik JID.
     *
     * WhatsApp kini mengirim identitas pengguna sebagai LID ("2399@lid") alih-
     * alih nomor telepon. LID tidak bisa dipakai membalas; nomor aslinya ada
     * di `remoteJidAlt` ("628xx@s.whatsapp.net") dan hanya dipakai bila JID
     * memang LID.
     */
    private function senderPhone(string $remoteJid, mixed $remoteJidAlt): ?string
    {
        $raw = str_ends_with($remoteJid, '@lid')
            ? (is_string($remoteJidAlt) ? $remoteJidAlt : '')
            : $remoteJid;

        // Domain dibuang, begitu pula nomor perangkat di belakang ':'.
        // Normalisasi menyapu seluruh karakter bukan angka, jadi
        // "628xx:12@s.whatsapp.net" tanpa pemotongan berubah menjadi nomor
        // yang tidak pernah ada.
        $user = strtok(strtok($raw, '@') ?: '', ':') ?: '';

        return PhoneNumber::normalize($user);
    }

    private function respond(string $reason, int $status = 200): JsonResponse
    {
        return response()->json(['data' => ['status' => $reason], 'meta' => []], $status);
    }
}
