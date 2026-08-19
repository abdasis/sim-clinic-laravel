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
            // Default mati; menyalakan chatbot bukan berarti membuka pintu
            // tulis ke tabel pasien.
            'allow_self_registration' => ['nullable', 'boolean'],
            'agent_name' => ['nullable', 'string', 'max:100'],
            // Null berarti fiturnya mati. Batas bawahnya satu menit supaya
            // tidak ada yang bisa menyetelnya jadi penutup yang datang
            // seketika, dan batas atasnya sehari karena lewat dari itu
            // percakapannya sudah tidak diingat pasien.
            'closing_idle_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'closing_message' => ['nullable', 'string', 'max:500'],
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
            'allow_self_registration' => __('chatbot.allow_self_registration'),
            'agent_name' => __('chatbot.agent_name'),
            'closing_idle_minutes' => __('chatbot.closing_idle_minutes'),
            'closing_message' => __('chatbot.closing_message'),
            'bookable_service_ids' => __('chatbot.bookable_services'),
        ];
    }
}
