<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

class SendInboxReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Conversation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            // Emoji kosong mencabut reaksi yang sudah terpasang.
            'emoji' => ['nullable', 'string', 'max:8'],
        ];
    }

    public function attributes(): array
    {
        return [
            'emoji' => __('inbox.reaction'),
        ];
    }
}
