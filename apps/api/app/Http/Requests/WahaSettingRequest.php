<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WahaSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'base_url' => ['required', 'url', 'max:255'],
            // Kosong berarti pertahankan kunci yang tersimpan: menyimpan
            // ulang alamatnya saja tidak boleh diam-diam mencabut kredensial.
            'api_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'base_url' => __('waha.base_url'),
            'api_key' => __('waha.api_key'),
        ];
    }
}
