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

        // Hanya percakapan berdua yang boleh dijawab. Status WhatsApp, grup,
        // saluran, dan daftar siaran semuanya tiba lewat webhook yang sama;
        // tanpa saringan ini, orang yang memasang status berisi kabar duka
        // atau tautan TikTok akan menerima balasan pribadi dari klinik
        // padahal ia tidak pernah mengirim pesan apa pun ke klinik.
        $chatJid = (string) ($payload['from'] ?? $payload['_data']['key']['remoteJid'] ?? '');

        if (! $this->isDirectChat($chatJid, $payload)) {
            Log::channel('chatbot')->warning('Pesan masuk ditolak: bukan percakapan berdua', [
                'session' => $session,
                'from' => $chatJid,
                'payload' => $payload,
            ]);

            return $this->accepted('bukan percakapan berdua');
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
        // _data.key.remoteJidAlt (format "628xx@s.whatsapp.net").
        //
        // Penggantinya hanya dipakai saat `from` memang berupa LID. Kalau
        // dipakai tanpa syarat, payload yang `from`-nya bukan nomor seseorang
        // — status, grup, siaran — tetap bisa menyelundupkan nomor lewat
        // kolom alt dan berbalas ke orang yang tidak pernah menghubungi klinik.
        $remoteJidAlt = (string) ($payload['_data']['key']['remoteJidAlt'] ?? '');
        $rawFrom = str_ends_with($chatJid, '@lid') && $remoteJidAlt !== ''
            ? $remoteJidAlt
            : $chatJid;

        $phone = PhoneNumber::normalize($rawFrom);

        if ($phone === null) {
            Log::channel('chatbot')->warning('Pesan masuk ditolak: nomor pengirim tidak valid', [
                'session' => $session,
                'from' => $chatJid,
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
     * Percakapan berdua, bukan status/grup/saluran/siaran.
     *
     * Dua penjaga sekaligus, karena payload tiap engine WAHA berbeda bentuk.
     * Pertama alamat obrolannya sendiri: hanya JID pengguna yang diterima.
     * Kedua keberadaan `participant` — kolom itu menyebut "siapa yang menulis
     * di dalam ruang bersama", jadi kehadirannya sudah cukup menandakan pesan
     * ini bukan berasal dari obrolan berdua, apa pun bentuk alamatnya.
     *
     * @param  array<string, mixed>  $payload
     */
    private function isDirectChat(string $chatJid, array $payload): bool
    {
        if ($chatJid === '') {
            return false;
        }

        $participant = $payload['participant'] ?? $payload['_data']['key']['participant'] ?? null;

        if (filled($participant)) {
            return false;
        }

        // Payload lama menyebut nomor tanpa domain sama sekali. Itu tidak
        // pernah berarti ruang bersama, jadi diloloskan ke pemeriksaan nomor
        // di bawah alih-alih ditolak di sini.
        if (! str_contains($chatJid, '@')) {
            return true;
        }

        foreach (['@g.us', '@broadcast', '@newsletter'] as $suffix) {
            if (str_ends_with($chatJid, $suffix)) {
                return false;
            }
        }

        return str_ends_with($chatJid, '@c.us')
            || str_ends_with($chatJid, '@s.whatsapp.net')
            || str_ends_with($chatJid, '@lid');
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
