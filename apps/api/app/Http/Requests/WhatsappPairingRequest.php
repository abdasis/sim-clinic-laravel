<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class WhatsappPairingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Meminta kode pairing berarti mengubah nomor yang tertaut ke sesi
        // klinik, bukan sekadar melihat keadaannya.
        return $this->user()?->can('update', Broadcast::class) ?? false;
    }

    /**
     * Nomor dinormalkan lebih dulu supaya aturan di bawah hanya menghadapi
     * satu bentuk: 62 diikuti 9-13 digit.
     */
    protected function prepareForValidation(): void
    {
        $phone = PhoneNumber::normalize($this->input('phone'));

        if ($phone !== null) {
            $this->merge(['phone' => $phone]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:15', 'regex:/^62\d{9,13}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone' => __('broadcast.test_phone_label'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('broadcast.invalid_phone'),
        ];
    }
}
