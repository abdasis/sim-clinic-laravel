<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessInboundMessageJob;
use App\Models\WhatsappSetting;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Pintu masuk pesan WhatsApp dari gateway.
 *
 * Route-nya publik dan di luar grup {tenant}: gateway tidak mengenal slug
 * klinik maupun sesi login. Yang menentukan pesan ini milik klinik mana adalah
 * nama sesi pada payload — sesi itulah yang tiap klinik daftarkan sendiri.
 *
 * Balasannya selalu cepat dan selalu 200 selama tokennya sah. Gateway akan
 * mengirim ulang apa pun yang tidak dibalas 200, jadi keadaan "pesan ini
 * memang tidak perlu diproses" tidak boleh dijawab dengan galat.
 */
class InboundMessageController extends Controller
{
    public function store(Request $request, string $token): JsonResponse
    {
        $expected = config('waha.webhook_token');

        // Token kosong berarti belum disetel; menerima apa pun dalam keadaan
        // itu sama dengan membuka webhook untuk siapa saja.
        if (blank($expected) || ! hash_equals((string) $expected, $token)) {
            abort(404);
        }

        $session = $request->input('session');
        $payload = (array) $request->input('payload', []);

        Log::channel('chatbot')->debug('Pesan masuk dari WAHA', [
            'session' => $session,
            'payload_keys' => array_keys($payload),
            'raw' => $request->all(),
        ]);

        if (! is_string($session) || $session === '') {
            Log::channel('chatbot')->warning('Pesan masuk ditolak: sesi tidak disebut', ['payload' => $payload]);

            return $this->accepted('sesi tidak disebut');
        }

        $setting = WhatsappSetting::withoutGlobalScopes()->where('session', $session)->first();

        if ($setting === null) {
            Log::channel('chatbot')->warning('Pesan masuk ditolak: sesi tidak dikenal', ['session' => $session]);

            return $this->accepted('sesi tidak dikenal');
        }

        // Pesan dari klinik sendiri akan memicu balasan atas balasan sendiri.
        if ($payload['fromMe'] ?? false) {
            Log::channel('chatbot')->debug('Pesan masuk dilewati: pesan keluar sendiri', ['session' => $session]);

            return $this->accepted('pesan keluar');
        }

        $body = $payload['body'] ?? null;

        // Media belum didukung; membalas gambar dengan tebakan teks lebih buruk
        // daripada tidak membalas sama sekali.
        if (($payload['hasMedia'] ?? false) || ! is_string($body) || trim($body) === '') {
            Log::channel('chatbot')->debug('Pesan masuk dilewati: bukan pesan teks', [
                'session' => $session,
                'hasMedia' => $payload['hasMedia'] ?? false,
                'body' => $body,
            ]);

            return $this->accepted('bukan pesan teks');
        }

        // Nomor pengirim. WhatsApp kini mengirim identitas pengguna sebagai
        // LID (contoh "239959873220620@lid") alih-alih nomor telepon langsung.
        // LID tidak bisa dipakai membalas; nomor aslinya disediakan WAHA di
        // _data.key.remoteJidAlt (format "628xx@s.whatsapp.net"). Dipakai dulu
        // kalau ada, dan `from` hanya fallback untuk payload lama.
        $rawFrom = (string) ($payload['from'] ?? '');
        $remoteJidAlt = (string) ($payload['_data']['key']['remoteJidAlt'] ?? '');

        if ($remoteJidAlt !== '') {
            $rawFrom = $remoteJidAlt;
        }

        $phone = PhoneNumber::normalize($rawFrom);

        if ($phone === null) {
            Log::channel('chatbot')->warning('Pesan masuk ditolak: nomor pengirim tidak valid', [
                'session' => $session,
                'from' => $payload['from'] ?? null,
                'remoteJidAlt' => $remoteJidAlt,
            ]);

            return $this->accepted('nomor pengirim tidak valid');
        }

        Log::channel('chatbot')->info('Pesan masuk diterima, job di-dispatch', [
            'tenant_id' => $setting->tenant_id,
            'session' => $session,
            'from' => $phone,
            'body' => trim($body),
        ]);

        ProcessInboundMessageJob::dispatch($setting->tenant_id, $phone, trim($body));

        return $this->accepted('diterima');
    }

    /**
     * Alasannya sudah dicatat per cabang di atas, lengkap dengan konteksnya;
     * di sini cukup jawaban 200-nya. Gateway mengirim ulang apa pun yang tidak
     * dibalas 200, jadi "memang tidak perlu diproses" tidak boleh jadi galat.
     */
    private function accepted(string $reason): JsonResponse
    {
        return response()->json(['data' => ['status' => $reason], 'meta' => []]);
    }
}
