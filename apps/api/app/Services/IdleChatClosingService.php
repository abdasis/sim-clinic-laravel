<?php

namespace App\Services;

use App\Actions\Chatbot\CloseIdleConversationAction;
use App\Actions\Chatbot\FindIdleConversationsAction;
use App\Actions\Chatbot\FindPatientByPhoneAction;
use App\Models\ChatbotSetting;
use App\Models\ChatMessage;
use App\Models\CompanyProfileSetting;
use App\Support\WahaClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Menutup percakapan chatbot yang ditinggal diam.
 *
 * Yang membedakan penutup ini dari balasan otomatis biasa: ia tahu apakah
 * bola masih di tangan pasien. Kalau pesan terakhir bot berupa pertanyaan,
 * penutupnya mengakui pertanyaan itu belum terjawab dan menawarkan
 * melanjutkan — bukan berpamitan seolah semuanya sudah beres.
 */
class IdleChatClosingService
{
    public function __construct(
        private readonly FindIdleConversationsAction $finder,
        private readonly CloseIdleConversationAction $closer,
        private readonly FindPatientByPhoneAction $patients,
    ) {}

    /**
     * Jalankan satu putaran untuk klinik yang sedang aktif di container.
     *
     * @return int jumlah percakapan yang benar-benar ditutup
     */
    public function run(?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        $setting = ChatbotSetting::query()->first();

        if ($setting === null || ! $setting->is_active) {
            return 0;
        }

        $idleMinutes = (int) $setting->closing_idle_minutes;

        if ($idleMinutes < 1) {
            return 0;
        }

        $waha = app(WahaClient::class);

        if ($waha === null) {
            return 0;
        }

        // Pesan penutup tengah malam terbaca sebagai gangguan, bukan layanan.
        // Klinik yang belum mengisi jam operasionalnya dianggap selalu buka,
        // mengikuti pola yang sudah dipakai di seluruh modul profil.
        if (! $this->clinicIsOpen($now)) {
            return 0;
        }

        $closed = 0;

        foreach ($this->finder->handle($idleMinutes, $now) as $last) {
            $sent = $this->closer->handle($waha, $last->sender_phone, $this->compose($setting, $last));

            if ($sent !== null) {
                $closed++;
            }
        }

        return $closed;
    }

    private function clinicIsOpen(Carbon $now): bool
    {
        $profile = CompanyProfileSetting::query()->first();

        return $profile === null || $profile->isOpenAt($now);
    }

    /**
     * Susun kalimat penutupnya.
     *
     * Teks racikan klinik menang atas teks bawaan, tapi placeholder-nya sama
     * supaya klinik tetap bisa menyapa nama pasien tanpa menulis kode.
     */
    private function compose(ChatbotSetting $setting, ChatMessage $last): string
    {
        $replacements = [
            'name' => $this->greetingName($last->sender_phone),
            'clinic' => app('tenant')->name,
        ];

        if (filled($setting->closing_message)) {
            return Str::swap(
                collect($replacements)->mapWithKeys(fn ($value, $key) => [':'.$key => $value])->all(),
                $setting->closing_message,
            );
        }

        return __($this->awaitingAnswer($last) ? 'chatbot.idle_closing_pending' : 'chatbot.idle_closing', $replacements);
    }

    /**
     * Nama panggilan pasien, atau sapaan umum bila nomornya belum terdaftar.
     *
     * Hanya kata pertama yang dipakai: "Halo Siti Nurhaliza Binti Ahmad,"
     * terbaca seperti surat tagihan, bukan seperti obrolan.
     */
    private function greetingName(string $senderPhone): string
    {
        $patient = $this->patients->handle($senderPhone);

        return $patient === null
            ? __('chatbot.idle_closing_name_fallback')
            : Str::of($patient->name)->trim()->explode(' ')->first();
    }

    /**
     * Pesan terakhir bot berupa pertanyaan?
     *
     * ponytail: dideteksi dari tanda tanya, bukan dari niat modelnya. Cukup
     * untuk membedakan "ada lagi yang bisa dibantu" dari "jam berapa mau
     * datang", dan tidak menambah satu panggilan AI hanya demi satu kalimat.
     * Ganti dengan penanda eksplisit dari tool call bila nanti alur bookingnya
     * perlu diingat lebih tepat.
     */
    private function awaitingAnswer(ChatMessage $last): bool
    {
        return str_contains($last->content, '?');
    }
}
