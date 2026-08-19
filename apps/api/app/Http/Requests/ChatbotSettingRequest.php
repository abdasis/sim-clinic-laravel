<?php

namespace App\Http\Requests;

use App\Rules\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class ChatbotSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'agent_name' => ['nullable', 'string', 'max:100'],
            // Kosong berarti seluruh layanan boleh dibooking; bukan larangan.
            'bookable_service_ids' => ['nullable', 'array'],
            'bookable_service_ids.*' => [TenantRule::exists('services')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'is_active' => __('chatbot.is_active'),
            'agent_name' => __('chatbot.agent_name'),
            'bookable_service_ids' => __('chatbot.bookable_services'),
        ];
    }
}
