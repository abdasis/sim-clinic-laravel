<?php

namespace App\Http\Requests;

use App\Enums\ClinicRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class InvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'role' => ['required', 'in:member,tenant_admin'],
            'clinic_role' => ['nullable', new Enum(ClinicRole::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => __('tenant.email'),
            'role' => __('tenant.user_role'),
            'clinic_role' => __('staff.clinic_role'),
        ];
    }
}
