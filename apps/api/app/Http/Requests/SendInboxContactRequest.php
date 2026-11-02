<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

class SendInboxContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Conversation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\+?[1-9]\d{7,14}$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('inbox.contact_name'),
            'phone' => __('inbox.contact_phone'),
        ];
    }
}
