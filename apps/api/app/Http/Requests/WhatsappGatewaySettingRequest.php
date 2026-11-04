<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WhatsappGatewaySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'base_url' => ['required', 'url', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'base_url' => __('whatsapp_gateway.base_url'),
        ];
    }
}
