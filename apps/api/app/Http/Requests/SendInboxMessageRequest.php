<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

class SendInboxMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Conversation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:4096'],
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => __('inbox.message_body'),
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => __('inbox.message_required'),
        ];
    }
}
