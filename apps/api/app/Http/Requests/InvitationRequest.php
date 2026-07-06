<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'role' => ['required', 'in:member,tenant_admin'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => __('tenant.email'),
            'role' => __('tenant.user_role'),
        ];
    }
}
