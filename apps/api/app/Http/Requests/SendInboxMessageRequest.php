<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendInboxMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Conversation::class) ?? false;
    }

    public function rules(): array
    {
        $conversation = $this->route('conversation');

        return [
            'body' => ['required', 'string', 'max:4096'],
            // Pesan yang dikutip wajib milik percakapan yang sama: id dari
            // utas klinik lain tidak boleh ditebak lalu dibalas.
            'reply_to_message_id' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')
                    ->where('conversation_id', $conversation instanceof Conversation ? $conversation->id : null),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => __('inbox.message_body'),
            'reply_to_message_id' => __('inbox.reply_to'),
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => __('inbox.message_required'),
            'reply_to_message_id.exists' => __('inbox.reply_not_found'),
        ];
    }
}
