<?php

namespace App\Http\Requests;

use App\Rules\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class BookingReminderSettingRequest extends FormRequest
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
            // Batas atasnya seminggu: lebih jauh dari itu pengingatnya sudah
            // terlupakan lagi sebelum harinya tiba.
            'offset_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'message_template_id' => ['nullable', TenantRule::exists('message_templates')],
            'confirmation_template_id' => ['nullable', TenantRule::exists('message_templates')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'is_active' => __('booking.reminder_active'),
            'offset_minutes' => __('booking.reminder_offset'),
            'message_template_id' => __('booking.reminder_template'),
            'confirmation_template_id' => __('booking.confirmation_template'),
        ];
    }
}
