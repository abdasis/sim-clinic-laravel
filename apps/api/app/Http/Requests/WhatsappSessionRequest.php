<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use App\Models\WhatsappSetting;
use App\Support\TemplateVariables;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WhatsappSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Mengubah sesi yang sudah ada, bukan membuat broadcast baru. Izin
        // buat lebih longgar daripada izin ubah di sebagian peran klinik, jadi
        // kategori yang salah di sini berarti pengaturan pengiriman bisa
        // disentuh oleh peran yang tidak seharusnya.
        return $this->user()?->can('update', Broadcast::class) ?? false;
    }

    public function rules(): array
    {
        $currentId = WhatsappSetting::query()->first()?->id;

        return [
            // Kosong berarti klinik ini memang belum punya sesi WAHA —
            // chatbot dan kotak masuk memakainya, broadcast tidak lagi.
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
                Rule::unique('whatsapp_settings', 'session')->ignore($currentId),
            ],
            // Nomor pengirim Cloud API Meta. Angka saja karena Meta memakai
            // id numerik, dan id itu satu-satunya petunjuk klinik mana yang
            // harus menerima pesan masuk dari webhooknya.
            'phone_number_id' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^\d+$/',
                Rule::unique('whatsapp_settings', 'phone_number_id')->ignore($currentId),
            ],
            // Akun WhatsApp Business yang menampung template resmi klinik.
            'waba_id' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^\d+$/',
                Rule::unique('whatsapp_settings', 'waba_id')->ignore($currentId),
            ],
            'reminder_template' => ['nullable', 'array'],
            'reminder_template.name' => ['required_with:reminder_template', 'string', 'max:255'],
            'reminder_template.language' => ['required_with:reminder_template', 'string', 'max:20'],
            'reminder_template.body' => ['required_with:reminder_template', 'string', 'max:4000'],
            'reminder_template.header_type' => ['nullable', Rule::in(TemplateVariables::HEADER_TYPES)],
            'reminder_template.params' => ['nullable', 'array', 'max:10'],
            'reminder_template.params.*' => ['string', Rule::in(TemplateVariables::KEYS)],
        ];
    }

    public function attributes(): array
    {
        return [
            'session' => __('broadcast.session_label'),
            'phone_number_id' => __('broadcast.phone_number_id_label'),
            'waba_id' => __('broadcast.waba_id_label'),
            'reminder_template' => __('broadcast.reminder_template_label'),
        ];
    }

    public function messages(): array
    {
        return [
            'session.regex' => __('broadcast.session_format'),
            'session.unique' => __('broadcast.session_taken'),
            'phone_number_id.regex' => __('broadcast.phone_number_id_format'),
            'phone_number_id.unique' => __('broadcast.phone_number_id_taken'),
            'waba_id.regex' => __('broadcast.waba_id_format'),
            'waba_id.unique' => __('broadcast.waba_id_taken'),
        ];
    }
}
