<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Setelan gateway milik satu klinik, disimpan pengelola platform dari
 * dashboard central. API key boleh kosong: artinya kredensial lama
 * dipertahankan, bukan dihapus.
 */
class WhatsappClinicSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'api_key' => ['nullable', 'string', 'min:8', 'max:255'],
            'session' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]{3,64}$/'],
            'daily_message_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'api_key' => __('whatsapp_gateway.api_key'),
            'session' => __('whatsapp_gateway.session'),
            'daily_message_limit' => __('whatsapp_gateway.daily_message_limit'),
        ];
    }
}
