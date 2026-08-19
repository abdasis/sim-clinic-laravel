<?php

namespace App\Actions\Chatbot;

use App\Actions\LogAuditAction;
use App\Actions\Patient\CreatePatientAction;
use App\Models\ChatbotSetting;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * Daftarkan pasien atas permintaannya sendiri lewat chat.
 *
 * Berbeda dari pendaftaran oleh admin, di sini tidak ada siapa pun yang
 * meninjau. Karena itu peringatan tidak cukup: yang meragukan ditolak, bukan
 * dicatat lalu diteruskan.
 *
 * Semua penolakan kembali sebagai teks alasan, bukan exception — yang
 * memanggil adalah AI, dan kalimat yang bisa dibacanya akan sampai ke pasien
 * apa adanya.
 */
class RegisterPatientAction
{
    /** Percakapan pendaftaran per nomor per jam. */
    private const RATE_LIMIT = 5;

    private const RATE_WINDOW = 3600;

    /**
     * Nomor WhatsApp-nya datang terpisah dari $arguments dan memang harus
     * begitu: yang boleh menentukannya cuma gateway, bukan AI. Kalau ia jadi
     * parameter tool, satu kalimat pasien sudah cukup untuk mendaftarkan
     * nomor orang lain.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{patient: ?Patient, error: ?string}
     */
    public function handle(string $senderPhone, array $arguments): array
    {
        $setting = ChatbotSetting::query()->first();

        if ($setting === null || ! $setting->allow_self_registration) {
            return $this->reject(__('chatbot.registration_disabled'));
        }

        if (! $this->withinRateLimit($senderPhone)) {
            return $this->reject(__('chatbot.registration_rate_limited'));
        }

        // Diblokir, bukan sekadar diperingatkan: tanpa admin yang meninjau,
        // nomor ganda berarti dua kartu pasien untuk orang yang sama.
        if (app(FindPatientByPhoneAction::class)->handle($senderPhone) !== null) {
            return $this->reject(__('chatbot.already_registered'));
        }

        $data = $this->validate($arguments);

        if (is_string($data)) {
            return $this->reject($data);
        }

        return ['patient' => $this->create($senderPhone, $data), 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|string alasan penolakan bila tidak lolos
     */
    private function validate(array $arguments): array|string
    {
        $validator = Validator::make($arguments, [
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return match (true) {
                $validator->errors()->has('name') => __('chatbot.registration_name_required'),
                $validator->errors()->has('birth_date') => __('chatbot.registration_birth_date_invalid'),
                $validator->errors()->has('gender') => __('chatbot.registration_gender_invalid'),
                default => __('chatbot.registration_invalid'),
            };
        }

        return $validator->validated();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(string $senderPhone, array $data): Patient
    {
        $patient = app(CreatePatientAction::class)->handle([
            'name' => $data['name'],
            'birth_date' => isset($data['birth_date']) ? Carbon::parse($data['birth_date'])->toDateString() : null,
            'gender' => $data['gender'] ?? null,
            'address' => $data['address'] ?? null,
            // Bukan dari AI, melainkan dari nomor yang benar-benar mengirim.
            'whatsapp' => $senderPhone,
            'whatsapp_opt_in' => true,
        ]);

        // CreatePatientAction sudah mencatat pembuatannya, tapi tidak tahu
        // asalnya. Baris inilah yang membedakan pasien yang mendaftar sendiri
        // dari pasien yang diketik staf di meja depan.
        app(LogAuditAction::class)->handle(
            'patient.registered_via_chatbot',
            $patient,
            null,
            [
                'source' => 'chatbot',
                'attributes' => $patient->getAttributes(),
                'sender_phone' => $senderPhone,
            ],
            'Pasien '.$patient->name.' mendaftarkan diri sendiri lewat chat WhatsApp.',
        );

        return $patient;
    }

    private function withinRateLimit(string $senderPhone): bool
    {
        $key = 'chatbot-register:'.app('tenant')->id.'|'.$senderPhone;

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT)) {
            Log::warning('Pendaftaran mandiri lewat chatbot ditahan batas laju.', [
                'sender_phone' => $senderPhone,
                'tenant_id' => app('tenant')->id,
            ]);

            return false;
        }

        RateLimiter::hit($key, self::RATE_WINDOW);

        return true;
    }

    /**
     * @return array{patient: null, error: string}
     */
    private function reject(string $reason): array
    {
        return ['patient' => null, 'error' => $reason];
    }
}
