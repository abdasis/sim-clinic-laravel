<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use Illuminate\Foundation\Http\FormRequest;

class MessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->route('messageTemplate') !== null ? 'update' : 'create';

        return $this->user()?->can($ability, Broadcast::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('broadcast.template_name'),
            'body' => __('broadcast.template_body'),
        ];
    }
}
