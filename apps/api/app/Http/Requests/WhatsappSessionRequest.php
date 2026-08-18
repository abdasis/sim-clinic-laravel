<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WhatsappSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Broadcast::class) ?? false;
    }

    public function rules(): array
    {
        return [
            // Kosong berarti klinik ini memang belum punya sesi — pengiriman
            // otomatis mati, kirim manual lewat wa.me tetap jalan.
            // Formatnya mengikuti yang diterima WAHA: huruf kecil, angka, strip.
            'session' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
                // Satu sesi hanya boleh dipakai satu klinik: dua klinik yang
                // menunjuk sesi sama akan mengirim dari nomor yang sama.
                // Tanpa aturan ini yang muncul bukan pesan galat, melainkan
                // pelanggaran unique di basis data.
                Rule::unique('whatsapp_settings', 'session')
                    ->ignore(WhatsappSetting::query()->first()?->id),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'session' => __('broadcast.session_label'),
        ];
    }

    public function messages(): array
    {
        return [
            'session.regex' => __('broadcast.session_format'),
            'session.unique' => __('broadcast.session_taken'),
        ];
    }
}
