<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use Illuminate\Foundation\Http\FormRequest;

class BroadcastTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Broadcast::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32'],
            'message' => ['required', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone' => __('broadcast.test_phone_label'),
            'message' => __('broadcast.test_message_label'),
        ];
    }
}
