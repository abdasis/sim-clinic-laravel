<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WhatsappTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Mengubah template berarti mengubah apa yang boleh terkirim ke
        // pasien, jadi izinnya sama dengan izin ubah broadcast.
        return $this->user()?->can('update', Broadcast::class) ?? false;
    }

    public function rules(): array
    {
        return [
            // Aturan nama dari Meta: huruf kecil, angka, dan garis bawah.
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['required', 'string', 'max:20'],
            'category' => ['required', Rule::in(['MARKETING', 'UTILITY'])],
            'header_type' => ['nullable', Rule::in(['TEXT', 'IMAGE'])],
            // Header teks wajib punya isi; header gambar tidak menerima teks.
            'header_text' => [
                'nullable',
                'string',
                'max:60',
                Rule::requiredIf(fn (): bool => $this->input('header_type') === 'TEXT'),
            ],
            'body' => ['required', 'string', 'max:1024'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => __('broadcast.template_invalid_name'),
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('broadcast.template_name'),
            'language' => __('broadcast.template_language_label'),
            'category' => __('broadcast.template_category'),
            'header_type' => __('broadcast.template_header_type'),
            'header_text' => __('broadcast.template_header_text_label'),
            'body' => __('broadcast.template_body'),
        ];
    }
}
