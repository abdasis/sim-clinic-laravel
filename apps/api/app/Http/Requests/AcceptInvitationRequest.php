<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/'],
        ];
    }

    public function attributes(): array
    {
        return ['password' => __('auth.password')];
    }

    public function messages(): array
    {
        return ['password.regex' => __('validation.password_complexity')];
    }
}
