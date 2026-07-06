<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'company_name' => __('tenant.company_name'),
            'phone' => __('tenant.phone'),
            'email' => __('tenant.email'),
            'password' => __('auth.password'),
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => __('validation.password_complexity'),
        ];
    }
}
