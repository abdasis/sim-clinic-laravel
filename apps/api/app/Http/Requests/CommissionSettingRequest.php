<?php

namespace App\Http\Requests;

use App\Enums\ClinicRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommissionSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('expense.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            // Minimal satu peran: daftar kosong membuat fee tidak pernah jatuh
            // ke siapa pun, dan itu hampir pasti bukan yang dimaksud.
            'eligible_roles' => ['required', 'array', 'min:1'],
            'eligible_roles.*' => ['required', Rule::in(ClinicRole::values())],
        ];
    }

    public function attributes(): array
    {
        return [
            'eligible_roles' => __('commission.eligible_roles'),
            'eligible_roles.*' => __('commission.eligible_roles'),
        ];
    }
}
