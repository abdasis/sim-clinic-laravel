<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'in:member,tenant_admin'],
        ];
    }

    public function attributes(): array
    {
        return [
            'role' => __('tenant.user_role'),
        ];
    }
}
