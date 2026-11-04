<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use App\Models\WhatsappSetting;
use App\Support\TemplateVariables;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            // Kosong berarti klinik ini belum punya sesi gateway; mengisinya
            // memulai alur koneksi. Formatnya mengikuti yang diterima gateway:
            // huruf kecil, angka, strip.
            'session' => [
                'nullable',
                'string',
                'regex:/^[a-z0-9-]{3,64}$/',
                // Satu sesi hanya boleh dipakai satu klinik: dua klinik yang
                // menunjuk sesi sama akan mengirim dari nomor yang sama.
                // Tanpa aturan ini yang muncul bukan pesan galat, melainkan
                // pelanggaran unique di basis data.
                Rule::unique('whatsapp_settings', 'session')->ignore($currentId),
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

    /**
     * Slug sesi terkunci setelah sesi gateway dibuat: gateway memetakan sesi
     * lewat slug itu, jadi menggantinya diam-diam meninggalkan sesi lama
     * yang masih tertaut nomor klinik.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $setting = WhatsappSetting::query()->first();

            if ($setting === null || blank($setting->gateway_session_id)) {
                return;
            }

            $session = $this->input('session');

            if ($this->has('session') && $session !== $setting->session) {
                $validator->errors()->add('session', __('broadcast.session_locked'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'session' => __('broadcast.session_label'),
            'reminder_template' => __('broadcast.reminder_template_label'),
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
