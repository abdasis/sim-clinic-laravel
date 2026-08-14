<?php

namespace App\Http\Requests;

use App\Enums\ClinicRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateStaffRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'clinic_role' => ['required', new Enum(ClinicRole::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'clinic_role' => __('staff.clinic_role'),
        ];
    }
}
