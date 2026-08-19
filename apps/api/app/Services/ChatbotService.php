<?php

namespace App\Services;

use App\Actions\Chatbot\FindPatientByPhoneAction;
use App\Actions\Chatbot\SaveChatMessageAction;
use App\Models\ChatbotSetting;
use App\Models\ChatMessage;
use App\Models\Patient;
use App\Support\ChatTools;
use App\Support\DeepSeekClient;
use App\Support\WhatsAppText;
use Illuminate\Support\Facades\Log;

/**
 * Orkestrasi satu putaran percakapan chatbot: dari pesan pasien sampai kalimat
 * balasan.
 *
 * Alurnya: susun konteks (aturan main + riwayat singkat) → tanya AI → jalankan
 * tool yang ia minta → tanya lagi dengan hasilnya → ulangi sampai ia berhenti
 * meminta tool. Yang keluar dari sini hanya teks; urusan mengirimnya ke
 * WhatsApp milik pemanggil.
 */
class ChatbotService
{
    /**
     * Alur booking terpanjang: cari layanan, cari staf, cek ketersediaan,
     * lalu buat bookingnya — empat putaran, plus satu untuk meralat diri.
     */
    private const MAX_TOOL_ROUNDS = 6;

    /** Riwayat sekadar konteks, bukan arsip — terlalu panjang malah mahal. */
    private const HISTORY_LIMIT = 10;

    public function __construct(
        private readonly ?DeepSeekClient $ai,
        private readonly SaveChatMessageAction $messages,
    ) {}

    /**
     * Balasan untuk satu pesan masuk, atau null bila chatbot memang tidak
     * seharusnya menjawab (dimatikan admin, atau penyedia AI belum disetel).
     */
    public function reply(string $senderPhone, string $incoming): ?string
    {
        $setting = ChatbotSetting::query()->first();

        if ($setting === null || ! $setting->is_active) {
            return null;
        }

        if ($this->ai === null) {
            Log::error('Chatbot aktif tetapi penyedia AI belum disetel.', [
                'tenant_id' => app('tenant')->id,
            ]);

            return null;
        }

        $patient = app(FindPatientByPhoneAction::class)->handle($senderPhone);

        $this->messages->handle($senderPhone, 'in', $incoming, 'user');

        $answer = $this->converse($setting, $senderPhone, $incoming, $patient);

        if ($answer !== null) {
            // Dirapikan sebelum disimpan, bukan sebelum dikirim saja: yang
            // tercatat harus persis teks yang dibaca pasien, kalau tidak
            // penelusuran keluhan berujung pada teks yang tidak pernah ada.
            $answer = WhatsAppText::normalize($answer);

            $this->messages->handle($senderPhone, 'out', $answer, 'assistant');
        }

        return $answer;
    }

    /**
     * Apakah chatbot klinik ini menyala.
     *
     * Dibuka supaya pemanggil bisa tahu sebelum memulai efek samping yang
     * terlihat pasien — indikator mengetik tidak boleh muncul untuk bot yang
     * memang tidak akan pernah menjawab.
     */
    public function isActive(): bool
    {
        return (bool) ChatbotSetting::query()->value('is_active');
    }

    /**
     * Putar percakapan sampai AI berhenti meminta tool.
     *
     * Batas putaran bukan sekadar jaga-jaga: model yang salah paham bisa
     * meminta tool yang sama berulang-ulang, dan tanpa batas itu satu pesan
     * pasien bisa menghabiskan kuota sepanjang hari.
     */
    private function converse(ChatbotSetting $setting, string $senderPhone, string $incoming, ?Patient $patient): ?string
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($setting, $patient)],
            ...$this->history($senderPhone),
            ['role' => 'user', 'content' => $incoming],
        ];

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $result = $this->ai->chatCompletion($messages, ChatTools::definitions());
            $message = $result['message'];
            $calls = is_array($message['tool_calls'] ?? null) ? $message['tool_calls'] : [];

            if ($calls === []) {
                $content = $message['content'] ?? null;

                return is_string($content) && trim($content) !== '' ? trim($content) : null;
            }

            $messages[] = $message;

            foreach ($calls as $call) {
                $messages[] = $this->runToolCall($call, $senderPhone, $patient);
            }
        }

        Log::error('Chatbot berhenti karena tool berputar terus.', [
            'sender_phone' => $senderPhone,
            'rounds' => self::MAX_TOOL_ROUNDS,
        ]);

        return __('chatbot.fallback');
    }

    /**
     * @param  array<string, mixed>  $call
     * @return array<string, mixed>
     */
    private function runToolCall(array $call, string $senderPhone, ?Patient $patient): array
    {
        $name = (string) ($call['function']['name'] ?? '');
        $arguments = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);

        if (! is_array($arguments)) {
            Log::error('Argumen tool chatbot bukan JSON yang sah.', [
                'tool' => $name,
                'raw' => $call['function']['arguments'] ?? null,
            ]);

            $arguments = [];
        }

        $output = ChatTools::execute($name, $arguments, $patient, $senderPhone);
        $encoded = json_encode($output, JSON_UNESCAPED_UNICODE) ?: '[]';

        // Hasil tool ikut tersimpan: kalau nanti ada pasien mengeluh diberi
        // harga yang salah, yang perlu dilihat adalah data apa yang waktu itu
        // benar-benar sampai ke AI.
        $this->messages->handle($senderPhone, 'out', $encoded, 'tool', $name, (string) ($call['id'] ?? ''));

        return [
            'role' => 'tool',
            'tool_call_id' => (string) ($call['id'] ?? ''),
            'content' => $encoded,
        ];
    }

    /**
     * Riwayat percakapan nomor ini saja, urut lama ke baru.
     *
     * Baris peran tool tidak ikut: tanpa pasangan tool_calls-nya, API menolak
     * seluruh permintaan. Yang berguna sebagai konteks memang kalimatnya.
     *
     * @return array<int, array<string, string>>
     */
    private function history(string $senderPhone): array
    {
        return ChatMessage::query()
            ->where('sender_phone', $senderPhone)
            ->whereIn('role', ['user', 'assistant'])
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn (ChatMessage $message): array => [
                'role' => (string) $message->role,
                'content' => (string) $message->content,
            ])
            ->values()
            ->all();
    }

    /**
     * Aturan main yang mengikat AI.
     *
     * Larangan mengarang ditulis paling tegas dan paling awal karena itulah
     * satu-satunya hal yang benar-benar berbahaya di sini: harga treatment yang
     * salah sebut akan ditagih pasien di meja kasir.
     */
    private function systemPrompt(ChatbotSetting $setting, ?Patient $patient): string
    {
        $clinic = app('tenant')->name;
        $agent = $setting->agent_name ?: __('chatbot.default_agent_name');
        $now = now();

        $lines = [
            "Kamu {$agent}, asisten WhatsApp resmi {$clinic}. Jawab dalam bahasa Indonesia yang ramah, sopan, dan ringkas.",
            // Tanpa ini model menebak tanggal dari data latihannya, dan tebakan
            // itu lolos penjaga "waktu belum lewat" karena biasanya memang
            // masih di masa depan — bookingnya mendarat di hari yang salah
            // tanpa ada yang menyadarinya.
            'Saat ini '.$now->translatedFormat('l, d F Y, H:i').'. Hitung "hari ini", "besok", "lusa", "minggu depan", dan sebutan waktu relatif lainnya dari saat itu. Jangan pernah menebak tanggal atau tahun sendiri.',
            'ATURAN PALING PENTING: kamu HANYA boleh menyebut harga, jadwal, nama layanan, nama staf, alamat, dan stok produk yang berasal dari hasil tool. Dilarang keras mengarang, menebak, atau memperkirakan. Bila hasil tool kosong, katakan terus terang bahwa datanya tidak tersedia dan tawarkan menghubungi klinik.',
            'Kamu hanya melayani topik seputar klinik ini: layanan, harga, jadwal, lokasi, produk, dan booking. Untuk topik lain, tolak dengan sopan dan arahkan kembali ke topik klinik. Jangan memberi saran medis, diagnosis, atau resep.',
            'Sebelum membuat booking, pastikan layanan, staf, dan waktunya sudah pasti. Bila ada yang belum jelas, tanyakan dulu ke pasien — jangan memanggil create_booking dengan tebakan.',
            'WAJIB panggil check_availability sebelum create_booking, untuk memastikan kliniknya buka dan slotnya benar-benar kosong. Jangan pernah menawarkan jam yang hasilnya tutup atau bentrok.',
            'Saat pasien bertanya jam buka atau tutup klinik, panggil get_clinic_info. Saat pasien bertanya promo atau diskon, panggil get_active_promos.',
            'Saat pasien menanyakan jadwalnya atau ingin membatalkan, panggil list_my_bookings dulu untuk mendapatkan booking_id, baru cancel_my_booking bila ia memang minta dibatalkan. Jangan pernah menebak booking_id.',
            'Jangan pernah menyebut kata "tool", "sistem", atau "database" ke pasien. Bicaralah seperti staf klinik yang sedang mengecek.',
        ];

        $lines[] = match (true) {
            $patient !== null => "Kamu sedang berbicara dengan {$patient->name}, pasien terdaftar. Sapa dengan namanya.",
            // Pendaftaran mandiri menyala: pasien tidak perlu lagi disuruh
            // datang ke klinik hanya untuk bisa booking.
            (bool) $setting->allow_self_registration => 'Nomor ini belum terdaftar sebagai pasien, tetapi klinik membuka pendaftaran lewat chat. '
                .'Tawarkan pasien mendaftar sekarang juga. Yang wajib ditanyakan hanya nama lengkap; tanggal lahir dan jenis kelamin boleh ditanyakan tapi tidak wajib, '
                .'dan jangan pernah menanyakan nomor WhatsApp — nomornya sudah diketahui dari chat ini. '
                .'Setelah data terkumpul, rangkum singkat lalu minta konfirmasi eksplisit ("Benar ingin saya daftarkan sebagai ...?"). '
                .'Panggil register_patient hanya setelah pasien menjawab setuju. Sesudah pendaftaran berhasil, pasien baru bisa dibuatkan booking.',
            default => 'Nomor ini belum terdaftar sebagai pasien. Kamu tetap boleh menjawab pertanyaan informasi, tapi booking lewat chat belum bisa diproses — arahkan pasien untuk mendaftar dulu ke klinik.',
        };

        return implode("\n\n", $lines);
    }
}
