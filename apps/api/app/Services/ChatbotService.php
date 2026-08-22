<?php

namespace App\Services;

use App\Actions\Chatbot\FindPatientByPhoneAction;
use App\Actions\Chatbot\SaveChatMessageAction;
use App\Models\ChatbotSetting;
use App\Models\ChatMessage;
use App\Models\Patient;
use App\Support\ChatTools;
use App\Support\ClinicIdentity;
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

    /**
     * Jeda yang dianggap "pasien kembali", bukan "percakapan berlanjut".
     *
     * ponytail: angkanya dipatok di sini. Pindahkan ke ChatbotSetting begitu
     * ada klinik yang ingin ambangnya berbeda.
     */
    private const INACTIVITY_THRESHOLD_MINUTES = 15;

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

        // Diukur sebelum pesan masuk ini ikut tersimpan; kalau sesudah, yang
        // terbaca sebagai "pesan terakhir" adalah pesan barusan dan jedanya
        // selalu nol.
        $returning = $this->hasBeenQuietFor($senderPhone);

        $this->messages->handle($senderPhone, 'in', $incoming, 'user');

        $answer = $this->converse($setting, $senderPhone, $incoming, $patient);

        if ($answer !== null) {
            // Dirapikan sebelum disimpan, bukan sebelum dikirim saja: yang
            // tercatat harus persis teks yang dibaca pasien, kalau tidak
            // penelusuran keluhan berujung pada teks yang tidak pernah ada.
            $answer = WhatsAppText::normalize($answer);

            if ($returning) {
                $answer .= "\n\n".__('chatbot.closing_offer');
            }

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
     * Pasien ini sempat lama tidak terdengar?
     *
     * Nomor yang belum punya riwayat sama sekali tidak dihitung: itu percakapan
     * pertama, bukan orang yang kembali setelah menghilang.
     */
    private function hasBeenQuietFor(string $senderPhone): bool
    {
        $last = ChatMessage::query()
            ->where('sender_phone', $senderPhone)
            ->latest('id')
            ->value('created_at');

        if ($last === null) {
            return false;
        }

        return now()->diffInMinutes($last, absolute: true) >= self::INACTIVITY_THRESHOLD_MINUTES;
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
        $clinic = ClinicIdentity::displayName();
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
            'Nilai `address` dari get_clinic_info sudah memuat tautan Google Maps-nya. Sebutkan seluruhnya apa adanya setiap kali pasien menanyakan alamat atau lokasi — jangan memotong tautannya, jangan mempersingkatnya, dan jangan menunggu pasien memintanya. Jangan pula mengulang tautan yang sama dua kali dalam satu balasan.',
            'Saat pasien bertanya jam buka atau tutup klinik, panggil get_clinic_info. Saat pasien bertanya promo atau diskon, panggil get_active_promos.',
            // Harga dari tool sudah memperhitungkan promo yang berjalan.
            // Tanpa baris ini model menyebut angkanya begitu saja, dan pasien
            // tidak pernah tahu ia sedang menerima harga promo — sekaligus
            // tidak tahu bahwa tawarannya ada batas waktunya.
            'Bila hasil tool memuat `promo`, harga yang tertulis di `price` SUDAH termasuk potongan. Sebutkan nama promonya, sebutkan `normal_price` sebagai harga biasa, dan sebutkan `ends_at` sebagai batas berlakunya. Jangan pernah menjumlahkan atau memotong harga sendiri — pakai angka dari tool apa adanya.',
            'Bila pasien menanyakan sebuah layanan atau produk yang ternyata sedang promo, tawarkan promonya walaupun ia tidak bertanya soal diskon. Bila tidak ada `promo` di hasil tool, jangan menyinggung diskon sama sekali.',
            // Dua pasangan tool yang paling sering tertukar: yang satu
            // menjawab angka, yang satu menjawab paragraf. Tanpa
            // dipisahkan setegas ini, model memakai tool harga untuk
            // menjawab "ini buat apa" lalu mengarang manfaatnya sendiri.
            'Saat pasien bertanya kegunaan, manfaat, atau detail sebuah produk, panggil get_product_info — bukan get_product_stock, yang hanya untuk stok dan harga.',
            'Saat pasien bertanya kegunaan, manfaat, atau detail sebuah layanan, panggil get_service_info — bukan search_services, yang hanya untuk harga dan durasi. Bila knowledge-nya kosong, katakan informasinya belum tersedia dan tawarkan menghubungi klinik; jangan mengarang manfaat treatment.',
            'Saat memberi tahu jadwal atau mengonfirmasi booking, sebutkan tanggal dan jam mulainya saja. Jangan menyebut durasi treatment, berapa menit layanannya, maupun jam selesainya — meski angkanya ada di hasil tool.',
            'Saat pasien menanyakan jadwalnya atau ingin membatalkan, panggil list_my_bookings dulu untuk mendapatkan booking_id, baru cancel_my_booking bila ia memang minta dibatalkan. Jangan pernah menebak booking_id.',
            'Jangan pernah menyebut kata "tool", "sistem", atau "database" ke pasien. Bicaralah seperti staf klinik yang sedang mengecek.',
        ];

        // Penyaringan sebenarnya terjadi di GetProductStockAction — angkanya
        // memang tidak sampai ke sini. Baris ini menutup celah satu-satunya
        // yang tersisa: model mengarang angka sendiri karena merasa perlu
        // menjawab dengan angka.
        if (! $setting->allows_stock_info) {
            $lines[] = 'Klinik ini tidak mengumumkan jumlah stok produknya. Dilarang menyebut angka atau perkiraan jumlah stok, '
                .'termasuk kata seperti "tinggal sedikit" atau "banyak". Bila pasien bertanya stok, jawab hanya "tersedia" atau '
                .'"sedang kosong" mengikuti penanda is_available dari hasil tool, lalu tawarkan menghubungi klinik bila ia butuh jumlah pastinya.';
        }

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
